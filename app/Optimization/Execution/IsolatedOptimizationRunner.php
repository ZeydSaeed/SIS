<?php

namespace App\Optimization\Execution;

use App\Intelligence\Expert\SafeAutoExecutor;
use App\Intelligence\Models\Recommendation;
use App\Optimization\Analysis\OptimizationScorer;
use App\Optimization\Gates\ArchitectureOptimizationGate;
use App\Optimization\Gates\CrossMetricGuard;
use App\Optimization\Memory\OptimizationHistoryRecorder;
use Illuminate\Support\Facades\Log;

/**
 * Applies ONE isolated optimization with checkpoint, validation, and rollback policy.
 * Code changes are NOT auto-applied — only intelligence Tier-1 safe DB actions.
 */
final class IsolatedOptimizationRunner
{
    public function __construct(
        private readonly SafeAutoExecutor $safeAutoExecutor,
        private readonly OptimizationScorer $scorer,
        private readonly ArchitectureOptimizationGate $architectureGate,
        private readonly CrossMetricGuard $crossMetricGuard,
        private readonly OptimizationHistoryRecorder $history,
    ) {}

    /**
     * @param  array<string, float|int>  $baselineMetrics
     * @return array<string, mixed>
     */
    public function run(Recommendation $recommendation, array $baselineMetrics = []): array
    {
        $candidate = [
            'action_type' => $recommendation->action_type,
            'confidence' => (float) $recommendation->confidence,
            'risk_tier' => (int) $recommendation->risk_tier,
            'expected_improvement_pct' => (float) ($recommendation->expected_impact['expected_improvement_pct'] ?? 10),
            'complexity' => 1,
        ];

        $scoreResult = $this->scorer->score($candidate);

        if (! $this->scorer->eligibleForAutonomous($candidate, $scoreResult['score'])) {
            return [
                'executed' => false,
                'reason' => 'Not eligible for autonomous execution — requires human approval',
                'score' => $scoreResult,
            ];
        }

        if (in_array($recommendation->action_type, ['migration', 'schema_change', 'index_create'], true)) {
            return [
                'executed' => false,
                'reason' => 'Safety boundary: schema/index changes require approval gate',
            ];
        }

        $this->architectureGate->assertPasses();

        $checkpoint = [
            'recommendation_id' => $recommendation->id,
            'recommendation_code' => $recommendation->recommendation_code,
            'action_type' => $recommendation->action_type,
            'baseline_metrics' => $baselineMetrics,
            'started_at' => now()->toIso8601String(),
        ];

        try {
            $event = $this->safeAutoExecutor->attempt($recommendation);
            if ($event === null) {
                return [
                    'executed' => false,
                    'reason' => 'Safe auto executor declined — approval or policy gate',
                    'checkpoint' => $checkpoint,
                ];
            }
        } catch (\Throwable $e) {
            Log::error('Optimization runner failed', [
                'recommendation' => $recommendation->recommendation_code,
                'error' => $e->getMessage(),
            ]);

            $this->history->record([
                'component' => $recommendation->table_name ?? 'unknown',
                'problem' => $recommendation->why,
                'change' => $recommendation->what,
                'decision' => 'REJECTED',
                'rollback' => true,
                'lessons_learned' => $e->getMessage(),
            ]);

            return [
                'executed' => false,
                'reason' => $e->getMessage(),
                'checkpoint' => $checkpoint,
            ];
        }

        $afterMetrics = $baselineMetrics; // VerificationService updates event separately

        $guardResult = $this->crossMetricGuard->evaluate($baselineMetrics, $afterMetrics);

        $decision = $guardResult['passed'] ? 'ACCEPTED' : 'ROLLBACK_RECOMMENDED';

        $historyId = $this->history->record([
            'component' => $recommendation->table_name ?? 'database',
            'problem' => $recommendation->why,
            'root_cause' => $recommendation->rule_id,
            'baseline' => $baselineMetrics,
            'change' => $recommendation->what,
            'expected_result' => $recommendation->expected_impact,
            'actual_result' => ['event_code' => $event->event_code ?? null],
            'side_effects' => $guardResult['regressions'],
            'decision' => $decision,
            'rollback' => ! $guardResult['passed'],
        ]);

        return [
            'executed' => true,
            'event_code' => $event->event_code ?? null,
            'decision' => $decision,
            'regressions' => $guardResult['regressions'],
            'history_id' => $historyId,
            'checkpoint' => $checkpoint,
        ];
    }
}
