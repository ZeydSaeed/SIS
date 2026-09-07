<?php

namespace App\Optimization\Execution;

use App\Intelligence\Models\Recommendation;
use App\Optimization\Analysis\OptimizationScorer;
use App\Optimization\Contracts\MetricSnapshot;
use App\Optimization\Contracts\OptimizationOperation;
use App\Optimization\Gates\ArchitectureOptimizationGate;
use App\Optimization\Gates\CrossMetricGuard;
use App\Optimization\Memory\OptimizationHistoryRecorder;
use App\Optimization\Rollback\RollbackManager;
use App\Optimization\SelfHealing\CheckpointService;
use App\Optimization\SelfHealing\CheckpointStatus;
use App\Optimization\SelfHealing\MetricSnapshotCapturer;
use App\Optimization\SelfHealing\SelfHealingEventLogger;
use Illuminate\Support\Facades\Log;

final class IsolatedOptimizationRunner
{
    public function __construct(
        private readonly OptimizationOperationRegistry $operations,
        private readonly OptimizationScorer $scorer,
        private readonly ArchitectureOptimizationGate $architectureGate,
        private readonly CrossMetricGuard $crossMetricGuard,
        private readonly OptimizationHistoryRecorder $history,
        private readonly MetricSnapshotCapturer $metricCapturer,
        private readonly CheckpointService $checkpoints,
        private readonly RollbackManager $rollbackManager,
        private readonly SelfHealingEventLogger $events,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(Recommendation $recommendation, MetricSnapshot $beforeMetrics, ?string $checkpointId = null): array
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

        $operation = $this->operations->forAction($recommendation->action_type);
        if ($operation === null) {
            return [
                'executed' => false,
                'reason' => 'No registered operation for action '.$recommendation->action_type,
            ];
        }

        $unknownCritical = $beforeMetrics->unavailableCriticalMetrics();
        if ($unknownCritical !== [] && config('optimization.gates.block_autonomous_on_unknown_critical', true)) {
            return [
                'executed' => false,
                'reason' => 'Critical metrics unavailable: '.implode(', ', $unknownCritical),
                'unknown_critical' => $unknownCritical,
            ];
        }

        $this->architectureGate->assertPasses();

        if ($checkpointId !== null) {
            $this->checkpoints->transition($checkpointId, CheckpointStatus::ExecutionStarted, [
                'recommendation_id' => $recommendation->id,
                'rollback_supported' => $operation->isRollbackSupported(),
                'restore_strategy' => $operation->restoreStrategy(),
            ]);
        }

        $checkpoint = [
            'recommendation_id' => $recommendation->id,
            'recommendation_code' => $recommendation->recommendation_code,
            'incident_id' => $recommendation->correlation_id,
            'action_type' => $recommendation->action_type,
            'target' => $recommendation->table_name,
            'rollback_supported' => $operation->isRollbackSupported(),
            'restore_strategy' => $operation->restoreStrategy(),
            'before_metrics' => $beforeMetrics->toArray(),
            'started_at' => now()->toIso8601String(),
        ];

        try {
            $applyResult = $operation->apply($recommendation);
            if (! ($applyResult['executed'] ?? false)) {
                if ($checkpointId !== null) {
                    $this->checkpoints->transition($checkpointId, CheckpointStatus::Rejected, [
                        'final_outcome' => 'EXECUTION_DECLINED',
                    ]);
                }

                return [
                    'executed' => false,
                    'reason' => $applyResult['reason'] ?? 'Operation declined',
                    'checkpoint' => $checkpoint,
                ];
            }

            $event = $applyResult['event'];
            if ($checkpointId !== null) {
                $this->checkpoints->transition($checkpointId, CheckpointStatus::Executed, [
                    'event_code' => $event?->event_code,
                ]);
            }

            $delay = (int) config('optimization.measurement.post_delay_seconds', 2);
            if ($delay > 0) {
                sleep($delay);
            }

            $afterMetrics = $this->metricCapturer->capture();
            if ($checkpointId !== null) {
                $this->checkpoints->transition($checkpointId, CheckpointStatus::AfterCaptured, [
                    'after_state' => $afterMetrics->toArray(),
                ]);
            }

            $guardResult = $this->crossMetricGuard->evaluate(
                $beforeMetrics->toGuardArray(),
                $afterMetrics->toGuardArray(),
            );

            if ($checkpointId !== null) {
                $this->checkpoints->transition($checkpointId, CheckpointStatus::GuardEvaluated, [
                    'guard_status' => $guardResult['passed'] ? 'passed' : 'failed',
                    'guard_result' => $guardResult,
                ]);
                $this->events->emit('CROSS_METRIC_EVALUATED', [
                    'checkpoint_id' => $checkpointId,
                    'passed' => $guardResult['passed'],
                    'regressions' => $guardResult['regressions'],
                ]);
            }

            if (! $guardResult['passed']) {
                $this->events->emit('CROSS_METRIC_FAILED', [
                    'checkpoint_id' => $checkpointId,
                    'recommendation_id' => $recommendation->id,
                    'regressions' => $guardResult['regressions'],
                ]);

                $decision = 'REJECTED';
                if ($operation->isRollbackSupported() && $event !== null) {
                    $this->rollbackManager->rollback(
                        $recommendation->action_type,
                        $recommendation,
                        $event,
                        array_merge($checkpoint, ['checkpoint_id' => $checkpointId]),
                    );
                    $decision = 'ROLLED_BACK';
                }

                if ($checkpointId !== null) {
                    $this->checkpoints->transition($checkpointId, CheckpointStatus::GuardRejected, [
                        'final_outcome' => $decision,
                        'rollback_status' => $operation->isRollbackSupported() ? 'attempted' : 'unsupported',
                    ]);
                }

                $historyId = $this->recordHistory($recommendation, $beforeMetrics, $afterMetrics, $event, $decision, $guardResult);

                return [
                    'executed' => false,
                    'decision' => $decision,
                    'reason' => 'Cross-metric guard failed',
                    'regressions' => $guardResult['regressions'],
                    'unknown_critical' => $guardResult['unknown_critical'],
                    'history_id' => $historyId,
                    'checkpoint' => $checkpoint,
                    'after_metrics' => $afterMetrics->toArray(),
                ];
            }

            $historyId = $this->recordHistory($recommendation, $beforeMetrics, $afterMetrics, $event, 'ACCEPTED', $guardResult);

            return [
                'executed' => true,
                'event_code' => $event?->event_code,
                'decision' => 'ACCEPTED',
                'rollback_supported' => $operation->isRollbackSupported(),
                'regressions' => [],
                'history_id' => $historyId,
                'checkpoint' => $checkpoint,
                'before_metrics' => $beforeMetrics->toArray(),
                'after_metrics' => $afterMetrics->toArray(),
            ];
        } catch (\Throwable $e) {
            Log::error('Optimization runner failed', [
                'recommendation' => $recommendation->recommendation_code,
                'error' => $e->getMessage(),
            ]);

            if ($checkpointId !== null) {
                $this->checkpoints->transition($checkpointId, CheckpointStatus::Incomplete, [
                    'final_outcome' => 'ERROR',
                    'error' => $e->getMessage(),
                ]);
            }

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
    }

    /**
     * @param  array<string, mixed>  $guardResult
     */
    private function recordHistory(
        Recommendation $recommendation,
        MetricSnapshot $before,
        MetricSnapshot $after,
        mixed $event,
        string $decision,
        array $guardResult,
    ): string {
        return $this->history->record([
            'component' => $recommendation->table_name ?? 'database',
            'recommendation_id' => $recommendation->id,
            'incident_id' => $recommendation->correlation_id,
            'problem' => $recommendation->why,
            'root_cause' => $recommendation->rule_id,
            'baseline' => $before->toArray(),
            'change' => $recommendation->what,
            'expected_result' => $recommendation->expected_impact,
            'actual_result' => [
                'event_code' => $event?->event_code,
                'after' => $after->toArray(),
            ],
            'side_effects' => $guardResult['regressions'] ?? [],
            'decision' => $decision,
            'rollback' => $decision === 'ROLLED_BACK' || $decision === 'REJECTED',
            'rollback_supported' => $this->rollbackManager->isRollbackSupported($recommendation->action_type),
        ]);
    }
}
