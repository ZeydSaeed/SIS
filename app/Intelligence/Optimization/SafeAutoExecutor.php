<?php

namespace App\Intelligence\Optimization;

use App\Database\SchemaHelper;
use App\Intelligence\Enums\OptimizationOutcome;
use App\Intelligence\Enums\RecommendationStatus;
use App\Intelligence\Enums\RiskTier;
use App\Intelligence\Governance\ApprovalGate;
use App\Intelligence\Governance\RiskPolicy;
use App\Intelligence\Jobs\VerifyOptimizationJob;
use App\Intelligence\Models\OptimizationEvent;
use App\Intelligence\Models\Recommendation;
use App\Intelligence\Support\CorrelationContext;
use App\Optimization\Execution\AnalyzeTargetPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SafeAutoExecutor
{
    public function __construct(
        private readonly RiskPolicy $riskPolicy,
        private readonly ApprovalGate $approvalGate,
        private readonly AnalyzeTargetPolicy $targetPolicy,
    ) {}

    public function attempt(Recommendation $recommendation): ?OptimizationEvent
    {
        $tier = RiskTier::from((int) $recommendation->risk_tier);

        if ($this->approvalGate->requiresApproval($recommendation)) {
            Log::info('Intelligence: recommendation requires approval', [
                'recommendation_code' => $recommendation->recommendation_code,
                'risk_tier' => $tier->value,
            ]);

            return null;
        }

        if (! $this->riskPolicy->canAutoExecute($tier)) {
            return null;
        }

        return match ($recommendation->action_type) {
            'analyze' => $this->executeAnalyze($recommendation),
            default => null,
        };
    }

    public function executeApproved(Recommendation $recommendation): ?OptimizationEvent
    {
        if ($recommendation->status !== RecommendationStatus::Approved->value) {
            return null;
        }

        return match ($recommendation->action_type) {
            'analyze' => $this->executeAnalyze($recommendation),
            default => null,
        };
    }

    private function executeAnalyze(Recommendation $recommendation): ?OptimizationEvent
    {
        if (! SchemaHelper::isPostgreSql()) {
            return null;
        }

        $target = $this->targetPolicy->resolveFromRecommendation($recommendation);
        if ($target === null) {
            Log::warning('SAFE_AUTO_EXECUTOR:TARGET_REJECTED', [
                'reason' => 'malformed_or_invalid_identifier',
                'schema' => $recommendation->schema_name,
                'table' => $recommendation->table_name,
            ]);

            return null;
        }

        if (! $this->targetPolicy->isAllowlisted($target['schema'], $target['table'])) {
            Log::warning('SAFE_AUTO_EXECUTOR:TARGET_REJECTED', [
                'reason' => 'allowlist_fail_closed',
                'target' => $target['qualified'],
            ]);

            return null;
        }

        $analyzeSql = $this->targetPolicy->toAnalyzeSql($target['schema'], $target['table']);
        if ($analyzeSql === null) {
            return null;
        }

        $before = $this->captureTableStats($target['schema'], $target['table']);

        $timeout = (int) config('optimization.analyze.execution_timeout_seconds', 30);
        if ($timeout > 0) {
            DB::statement("SET statement_timeout = '{$timeout}s'");
        }

        try {
            DB::statement("ANALYZE {$analyzeSql}");
        } catch (QueryException $e) {
            if ($this->isTimeout($e)) {
                Log::warning('SAFE_AUTO_EXECUTOR:EXECUTION_FAILED', [
                    'reason' => 'statement_timeout',
                    'target' => $target['qualified'],
                    'timeout_seconds' => $timeout,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }

            Log::error('SAFE_AUTO_EXECUTOR:EXECUTION_FAILED', [
                'reason' => 'query_exception',
                'target' => $target['qualified'],
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if ($timeout > 0) {
                DB::statement('RESET statement_timeout');
            }
        }

        $after = $this->captureTableStats($target['schema'], $target['table']);

        $event = OptimizationEvent::query()->create([
            'event_code' => 'OPT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'recommendation_id' => $recommendation->id,
            'type' => 'analyze',
            'rule_id' => $recommendation->rule_id,
            'risk_tier' => RiskTier::SafeAuto->value,
            'schema_name' => $target['schema'],
            'table_name' => $target['table'],
            'trigger' => $recommendation->evidence,
            'action_taken' => "ANALYZE {$target['qualified']}",
            'evidence_before' => $before,
            'evidence_after' => $after,
            'results' => null,
            'outcome' => OptimizationOutcome::Pending->value,
            'rollback_required' => false,
            'executed_by' => null,
            'approved_by' => $recommendation->approved_by,
            'context_fingerprint' => $this->contextFingerprint(),
            'recency_weight' => 1.0,
            'correlation_id' => CorrelationContext::id(),
            'executed_at' => now(),
        ]);

        $recommendation->update(['status' => RecommendationStatus::Executed->value]);

        VerifyOptimizationJob::dispatch($event->id)
            ->delay(now()->addMinutes((int) config('intelligence.verification.window_minutes', 15)));

        return $event;
    }

    private function isTimeout(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'statement timeout')
            || str_contains($message, 'canceling statement')
            || $e->getCode() === '57014';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function captureTableStats(string $schema, string $table): ?array
    {
        if (! SchemaHelper::isPostgreSql()) {
            return null;
        }

        $row = DB::selectOne('
            SELECT n_live_tup AS row_estimate, last_analyze, last_autoanalyze
            FROM pg_stat_user_tables
            WHERE schemaname = ? AND relname = ?
        ', [$schema, $table]);

        return $row ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function contextFingerprint(): array
    {
        return [
            'knowledge_version' => config('intelligence.knowledge_version'),
            'performance_budget_version' => config('intelligence.performance_budget_version'),
            'postgres' => SchemaHelper::isPostgreSql(),
            'captured_at' => now()->toIso8601String(),
        ];
    }
}
