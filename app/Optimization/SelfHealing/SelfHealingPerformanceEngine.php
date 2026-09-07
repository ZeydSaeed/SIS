<?php

namespace App\Optimization\SelfHealing;

use App\Optimization\Contracts\IncidentReport;
use App\Optimization\Contracts\MetricSnapshot;
use App\Optimization\Enums\OptimizationMode;
use App\Optimization\OptimizationEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class SelfHealingPerformanceEngine
{
    private const WORKER_LOCK = 'optimization:self-healing:worker';

    public function __construct(
        private readonly TelemetryCollector $telemetry,
        private readonly EnvironmentProfileService $environment,
        private readonly AdaptiveBaselineEngine $adaptiveBaseline,
        private readonly HealthScoreEngine $healthScore,
        private readonly AnomalyDetector $anomalyDetector,
        private readonly RootCauseAnalyzer $rootCauseAnalyzer,
        private readonly OptimizationEngine $optimizationEngine,
        private readonly CheckpointService $checkpoint,
        private readonly OptimizationTargetLock $targetLock,
        private readonly OptimizationCooldownManager $cooldown,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly StabilizationMonitor $stabilization,
        private readonly RollbackCoordinator $rollback,
        private readonly SelfHealingStateStore $stateStore,
        private readonly MetricSnapshotCapturer $metricCapturer,
        private readonly SelfHealingEventLogger $events,
        private readonly SelfHealingLearningRecorder $learning,
        private readonly CheckpointRecoveryService $checkpointRecovery,
        private readonly DataScaleContext $dataScale,
        private readonly AutonomousExecutionPolicy $autonomousPolicy,
        private readonly AutonomousRateLimiter $rateLimiter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function runCycle(): array
    {
        if (! config('optimization.enabled', true)) {
            return ['skipped' => true, 'reason' => 'optimization disabled'];
        }

        $cycleId = 'CYC-'.Str::upper(Str::random(8));
        $this->events->emit('SELF_HEALING_CYCLE_STARTED', ['cycle_id' => $cycleId]);

        try {
            return $this->executeCycle($cycleId);
        } catch (\Throwable $e) {
            $this->events->emit('SELF_HEALING_CYCLE_FAILED', ['cycle_id' => $cycleId, 'error' => $e->getMessage()]);

            $this->stateStore->mutate(function (array $state) use ($e) {
                $state['last_cycle_at'] = now()->toIso8601String();
                $state['last_cycle_outcome'] = 'error_contained';
                $state['last_failed_cycle_at'] = now()->toIso8601String();
                $state['last_error'] = $e->getMessage();

                return $state;
            });

            return [
                'outcome' => 'error_contained',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $state = $this->stateStore->read();
        $baseline = $this->adaptiveBaseline->load();
        $env = $this->environment->current();

        return [
            'mode' => $this->effectiveMode()->value,
            'configured_mode' => config('optimization.mode'),
            'unified_safety_pipeline' => config('optimization.unified_safety_pipeline', true),
            'safe_mode' => $state['safe_mode'] ?? false,
            'circuit_breaker_open' => $this->circuitBreaker->isOpen(),
            'safe_mode_reason' => $state['safe_mode_reason'] ?? null,
            'consecutive_failures' => $state['consecutive_failures'] ?? 0,
            'last_cycle_at' => $state['last_cycle_at'] ?? null,
            'last_successful_cycle_at' => $state['last_successful_cycle_at'] ?? null,
            'last_failed_cycle_at' => $state['last_failed_cycle_at'] ?? null,
            'last_cycle_outcome' => $state['last_cycle_outcome'] ?? null,
            'active_stabilizations' => count($state['active_stabilizations'] ?? []),
            'baseline_metrics' => count($baseline['metrics'] ?? []),
            'baseline_stale' => $baseline['stale'] ?? false,
            'baseline_stale_reason' => $baseline['stale_reason'] ?? null,
            'environment' => $env['environment'] ?? config('app.env'),
            'scheduler_defined' => true,
            'worker_lock_active' => Cache::has(self::WORKER_LOCK),
            'cooldowns' => $state['cooldowns'] ?? [],
            'last_telemetry_at' => $this->telemetry->collect()['collected_at'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        $telemetry = $this->telemetry->collect();
        $baseline = $this->adaptiveBaseline->load();
        $health = $this->healthScore->evaluate($telemetry, $baseline);
        $anomalies = $this->anomalyDetector->detect($telemetry, $health);

        return [
            'telemetry' => $telemetry,
            'health' => $health,
            'anomalies' => $anomalies,
            'anomaly_count' => count($anomalies),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function executeCycle(string $cycleId): array
    {
        $lockTtl = (int) config('optimization.worker.lock_ttl_seconds', 600);
        $lock = Cache::lock(self::WORKER_LOCK, $lockTtl);

        if (! $lock->get()) {
            return ['skipped' => true, 'reason' => 'another cycle in progress'];
        }

        try {
            $this->checkpointRecovery->recoverIncomplete();

            $previousEnv = $this->environment->current();
            $env = $this->environment->capture();
            if ($this->environment->hasEnvironmentChanged($previousEnv, $env)) {
                $this->adaptiveBaseline->markStale('environment_change');
                $this->events->emit('BASELINE_STALE', ['reason' => 'environment_change']);
            }

            if ($this->adaptiveBaseline->isStale()) {
                $outcome = [
                    'outcome' => 'baseline_warmup',
                    'message' => 'Baseline recalibration — no aggressive optimization',
                ];
                $this->optimizationEngine->observe();
                $telemetry = $this->telemetry->collect();
                $this->adaptiveBaseline->update($telemetry, []);
                $this->finalizeCycle($outcome);

                return $outcome;
            }

            $this->optimizationEngine->observe();

            $telemetry = $this->telemetry->collect();
            $baseline = $this->adaptiveBaseline->loadCompatible($telemetry);
            $health = $this->healthScore->evaluate($telemetry, $baseline);
            $anomalies = $this->anomalyDetector->detect($telemetry, $health);
            $this->adaptiveBaseline->update($telemetry, $anomalies);

            $this->writeHealthReport($health, $anomalies, $telemetry);
            $stabilizationResults = $this->stabilization->checkPending();

            $outcome = [
                'outcome' => 'monitor',
                'cycle_id' => $cycleId,
                'health_score' => $health['overall_score'],
                'health_status' => $health['status'],
                'anomalies' => count($anomalies),
                'stabilization' => $stabilizationResults,
                'safe_mode' => $this->circuitBreaker->isOpen(),
            ];

            if ($anomalies !== []) {
                $this->events->emit('ANOMALY_DETECTED', ['cycle_id' => $cycleId, 'count' => count($anomalies)]);

                $incident = $this->rootCauseAnalyzer->analyze($telemetry, $anomalies);
                $this->writeRootCauseReport($incident, $anomalies);

                if ($incident !== null) {
                    $this->events->emit('RCA_COMPLETED', [
                        'cycle_id' => $cycleId,
                        'incident_id' => $incident->incidentId,
                        'target' => $incident->target,
                        'confidence' => $incident->confidence,
                    ]);
                    $outcome['incident_id'] = $incident->incidentId;
                    $outcome['root_cause_confidence'] = $incident->confidence;

                    if ($this->effectiveMode() === OptimizationMode::Recommend) {
                        $recommend = $this->optimizationEngine->recommend();
                        $outcome['recommend'] = $recommend;
                        $outcome['outcome'] = 'recommend';
                    } else {
                        $policy = $this->evaluateAutonomousPolicy($incident, $cycleId);
                        $this->events->emit('AUTONOMOUS_POLICY_EVALUATED', $policy);

                        if ($policy['allowed']) {
                            $heal = $this->attemptSelfHeal($incident, $anomalies[0], $cycleId);
                            $outcome = array_merge($outcome, $heal);
                        } else {
                            $outcome['outcome'] = 'degraded_observed';
                            $outcome['self_heal'] = $policy['reason'] ?? 'autonomous policy denied';
                            $outcome['policy_code'] = $policy['code'] ?? 'denied';
                            $this->events->emit('AUTONOMOUS_POLICY_DENIED', [
                                'cycle_id' => $cycleId,
                                'incident_id' => $incident->incidentId,
                                'code' => $policy['code'] ?? 'denied',
                                'reason' => $policy['reason'] ?? null,
                            ]);
                            $this->events->emit('OPTIMIZATION_REJECTED', [
                                'cycle_id' => $cycleId,
                                'reason' => $outcome['self_heal'],
                                'policy_code' => $policy['code'] ?? 'denied',
                            ]);
                        }
                    }
                }
            }

            $this->finalizeCycle($outcome);

            return $outcome;
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{allowed: bool, code: string, reason: ?string, checks: array<string, string>}
     */
    private function evaluateAutonomousPolicy(IncidentReport $incident, string $cycleId): array
    {
        $target = $this->targetLock->normalize($incident->target, $incident->schemaName, $incident->tableName);

        return $this->autonomousPolicy->evaluate(
            incident: $incident,
            effectiveMode: $this->effectiveMode(),
            circuitOpen: $this->circuitBreaker->isOpen(),
            baselineStale: $this->adaptiveBaseline->isStale(),
            targetLocked: $this->targetLock->isLocked($incident->target, $incident->schemaName, $incident->tableName),
            onCooldown: $this->cooldown->isOnCooldown($incident->target),
            scaleBlocked: ! $this->dataScale->allowsAutonomousOptimization($this->dataScale->capture()),
            checkpointBlocks: $this->checkpointRecovery->blocksAutonomousRetry($target),
            metrics: $this->metricCapturer->capture(),
            normalizedTarget: $target,
            cycleId: $cycleId,
        );
    }

    /**
     * @param  array<string, mixed>  $primaryAnomaly
     * @return array<string, mixed>
     */
    private function attemptSelfHeal(IncidentReport $incident, array $primaryAnomaly, string $cycleId): array
    {
        $target = $this->targetLock->normalize($incident->target, $incident->schemaName, $incident->tableName);

        if (! $this->targetLock->acquire($target, $incident->schemaName, $incident->tableName)) {
            $this->events->emit('TARGET_LOCK_DENIED', ['target' => $target, 'incident_id' => $incident->incidentId]);

            return ['outcome' => 'locked', 'target' => $target];
        }

        $this->events->emit('TARGET_LOCK_ACQUIRED', ['target' => $target, 'incident_id' => $incident->incidentId]);

        try {
            $this->rateLimiter->recordExecution($target, $cycleId);

            $beforeMetrics = $this->metricCapturer->capture();

            $checkpointId = $this->checkpoint->create([
                'operation_id' => 'OP-'.Str::upper(Str::random(6)),
                'incident_id' => $incident->incidentId,
                'cycle_id' => $cycleId,
                'target' => $target,
                'action' => $incident->candidateActions[0] ?? 'analyze',
                'root_cause' => $incident->toArray(),
                'before_state' => $beforeMetrics->toArray(),
                'rollback_supported' => false,
                'restore_strategy' => 'non_reversible_safe',
                'anomaly' => $primaryAnomaly,
            ]);

            $this->events->emit('CHECKPOINT_CREATED', ['checkpoint_id' => $checkpointId, 'incident_id' => $incident->incidentId]);
            $this->events->emit('OPTIMIZATION_STARTED', ['incident_id' => $incident->incidentId, 'target' => $target, 'checkpoint_id' => $checkpointId]);
            $this->events->emit('EXECUTION_STARTED', ['incident_id' => $incident->incidentId, 'target' => $target, 'checkpoint_id' => $checkpointId]);

            $result = $this->optimizationEngine->runForIncident($incident, $checkpointId);

            if (! ($result['executed'] ?? false)) {
                $this->events->emit('EXECUTION_FAILED', ['incident_id' => $incident->incidentId, 'checkpoint_id' => $checkpointId, 'result' => $result]);
                $this->events->emit('OPTIMIZATION_REJECTED', ['incident_id' => $incident->incidentId, 'result' => $result, 'checkpoint_id' => $checkpointId]);
                $this->checkpoint->transition($checkpointId, CheckpointStatus::Rejected, [
                    'final_outcome' => 'REJECTED',
                    'result' => $result,
                ]);
                $this->rollback->handleFailedOptimization($result);
                $this->cooldown->startCooldown($target);
                $this->learning->record($incident, $beforeMetrics, null, $result, 'REJECTED');

                return [
                    'outcome' => 'heal_failed',
                    'target' => $target,
                    'checkpoint_id' => $checkpointId,
                    'result' => $result,
                ];
            }

            $this->circuitBreaker->recordSuccess();
            $this->cooldown->startCooldown($target);

            $afterMetrics = isset($result['after_metrics'])
                ? MetricSnapshot::fromArray($result['after_metrics'])
                : $this->metricCapturer->capture();

            $this->learning->record(
                $incident,
                $beforeMetrics,
                $afterMetrics,
                $result,
                (string) ($result['decision'] ?? 'ACCEPTED'),
            );

            $optimizationId = $result['history_id'] ?? $checkpointId;
            $this->stabilization->start($optimizationId, [
                'target' => $target,
                'incident_id' => $incident->incidentId,
                'checkpoint_id' => $checkpointId,
                'recommendation_id' => $result['checkpoint']['recommendation_id'] ?? null,
                'event_code' => $result['event_code'] ?? null,
                'before_metrics' => $beforeMetrics->toArray(),
            ]);

            $this->checkpoint->transition($checkpointId, CheckpointStatus::StabilizationStarted, [
                'stabilization_status' => 'started',
                'recommendation_id' => $result['checkpoint']['recommendation_id'] ?? null,
            ]);

            $this->events->emit('STABILIZATION_STARTED', ['incident_id' => $incident->incidentId, 'checkpoint_id' => $checkpointId]);
            $this->events->emit('EXECUTION_COMPLETED', ['incident_id' => $incident->incidentId, 'checkpoint_id' => $checkpointId, 'decision' => $result['decision'] ?? null]);
            $this->events->emit('OPTIMIZATION_COMPLETED', ['incident_id' => $incident->incidentId, 'decision' => $result['decision'] ?? null]);
            $this->writeSelfHealingStatus('ACCEPTED', $target, $result);

            return [
                'outcome' => 'heal_applied',
                'target' => $target,
                'checkpoint_id' => $checkpointId,
                'result' => $result,
                'stabilization_started' => true,
            ];
        } finally {
            $this->targetLock->release($target, $incident->schemaName, $incident->tableName);
        }
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    private function finalizeCycle(array $outcome): void
    {
        $this->stateStore->mutate(function (array $state) use ($outcome) {
            $state['last_cycle_at'] = now()->toIso8601String();
            $state['last_cycle_outcome'] = $outcome['outcome'];
            if (($outcome['outcome'] ?? '') !== 'error_contained') {
                $state['last_successful_cycle_at'] = now()->toIso8601String();
            }

            return $state;
        });
    }

    private function effectiveMode(): OptimizationMode
    {
        if ($this->circuitBreaker->isOpen()) {
            return OptimizationMode::Observe;
        }

        return OptimizationMode::tryFrom(config('optimization.mode', 'observe'))
            ?? OptimizationMode::Observe;
    }

    /**
     * @param  array<string, mixed>  $health
     * @param  list<array<string, mixed>>  $anomalies
     * @param  array<string, mixed>  $telemetry
     */
    private function writeHealthReport(array $health, array $anomalies, array $telemetry): void
    {
        $lines = [
            '# Health Status',
            '',
            'Generated: '.now()->toIso8601String(),
            'Overall Score: '.$health['overall_score'],
            'Status: '.$health['status'],
            '',
            '## Dimensions',
            '',
        ];

        foreach ($health['dimensions'] as $metric => $dim) {
            $lines[] = "- **{$metric}:** {$dim['status']} (current={$dim['current']}, baseline={$dim['baseline']})";
        }

        $lines[] = '';
        $lines[] = '## Anomalies';
        $lines[] = count($anomalies) === 0 ? '_None_' : json_encode($anomalies, JSON_PRETTY_PRINT);

        $this->writeReport('HEALTH-STATUS.md', implode("\n", $lines));
        $this->writeReport('PERFORMANCE-BASELINE.md', json_encode($telemetry, JSON_PRETTY_PRINT));
    }

    /**
     * @param  list<array<string, mixed>>  $anomalies
     */
    private function writeRootCauseReport(?IncidentReport $incident, array $anomalies): void
    {
        if ($incident === null) {
            return;
        }

        $content = "# Root Cause Report\n\nGenerated: ".now()->toIso8601String()."\n\n";
        $content .= json_encode($incident->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $content .= "\n\n## Anomalies\n\n".json_encode($anomalies, JSON_PRETTY_PRINT);

        $this->writeReport('ROOT-CAUSE-REPORT.md', $content);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function writeSelfHealingStatus(string $decision, string $target, array $result): void
    {
        $content = "# Self-Healing Status\n\n";
        $content .= 'Updated: '.now()->toIso8601String()."\n";
        $content .= "Decision: {$decision}\n";
        $content .= "Target: {$target}\n\n";
        $content .= json_encode($result, JSON_PRETTY_PRINT);

        $this->writeReport('SELF-HEALING-STATUS.md', $content);
    }

    private function writeReport(string $filename, string $content): void
    {
        $path = config('optimization.reports_path');
        File::ensureDirectoryExists($path);
        File::put("{$path}/{$filename}", $content);
    }
}
