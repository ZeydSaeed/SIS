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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SafeAutoExecutor
{
    public function __construct(
        private readonly RiskPolicy $riskPolicy,
        private readonly ApprovalGate $approvalGate,
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

        $qualified = $this->qualifiedTable($recommendation);
        if ($qualified === null) {
            return null;
        }

        $before = $this->captureTableStats($qualified);

        DB::statement("ANALYZE {$qualified}");

        $after = $this->captureTableStats($qualified);

        $event = OptimizationEvent::query()->create([
            'event_code' => 'OPT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'recommendation_id' => $recommendation->id,
            'type' => 'analyze',
            'rule_id' => $recommendation->rule_id,
            'risk_tier' => RiskTier::SafeAuto->value,
            'schema_name' => $recommendation->schema_name,
            'table_name' => $recommendation->table_name,
            'trigger' => $recommendation->evidence,
            'action_taken' => "ANALYZE {$qualified}",
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

    private function qualifiedTable(Recommendation $recommendation): ?string
    {
        if ($recommendation->schema_name && $recommendation->table_name) {
            return SchemaHelper::qualified($recommendation->schema_name, $recommendation->table_name);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function captureTableStats(string $qualified): ?array
    {
        if (! SchemaHelper::isPostgreSql()) {
            return null;
        }

        [$schema, $table] = explode('.', str_replace('"', '', $qualified), 2);

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
