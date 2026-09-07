<?php

namespace App\Optimization\SelfHealing;

use App\Optimization\Analysis\RootCauseReport;
use App\Optimization\Enums\OptimizationMode;
use App\Optimization\OptimizationEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Continuous self-monitoring, self-diagnosing, and self-healing orchestrator.
 * Built ON TOP of OptimizationEngine — does not replace Intelligence Layer.
 *
 * Failure containment: all cycles wrapped in try/catch — optimization failure
 * MUST NOT crash the application.
 */
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
    ) {}

    /**
     * Main continuous cycle — lightweight observe always; heal only when degraded.
     *
     * @return array<string, mixed>
     */
    public function runCycle(): array
    {
        if (! config('optimization.enabled', true)) {
            return ['skipped' => true, 'reason' => 'optimization disabled'];
        }

        try {
            return $this->executeCycle();
        } catch (\Throwable $e) {
            Log::error('Self-healing cycle failed — contained, application unaffected', [
                'error' => $e->getMessage(),
            ]);

            $this->stateStore->mutate(function (array $state) use ($e) {
                $state['last_cycle_at'] = now()->toIso8601String();
                $state['last_cycle_outcome'] = 'error_contained';
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
            'safe_mode' => $state['safe_mode'] ?? false,
            'safe_mode_reason' => $state['safe_mode_reason'] ?? null,
            'consecutive_failures' => $state['consecutive_failures'] ?? 0,
            'last_cycle_at' => $state['last_cycle_at'] ?? null,
            'last_cycle_outcome' => $state['last_cycle_outcome'] ?? null,
            'active_stabilizations' => count($state['active_stabilizations'] ?? []),
            'baseline_metrics' => count($baseline['metrics'] ?? []),
            'environment' => $env['environment'] ?? config('app.env'),
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
    private function executeCycle(): array
    {
        $lockTtl = (int) config('optimization.worker.lock_ttl_seconds', 600);
        $lock = Cache::lock(self::WORKER_LOCK, $lockTtl);

        if (! $lock->get()) {
            return ['skipped' => true, 'reason' => 'another cycle in progress'];
        }

        try {
            $previousEnv = $this->environment->current();
            $env = $this->environment->capture();
            if ($this->environment->hasEnvironmentChanged($previousEnv, $env)) {
                Log::info('Self-healing: environment change detected — baseline context updated');
            }

            $this->optimizationEngine->observe();

            $telemetry = $this->telemetry->collect();
            $health = $this->healthScore->evaluate($telemetry, $this->adaptiveBaseline->load());
            $anomalies = $this->anomalyDetector->detect($telemetry, $health);
            $adaptiveBaseline = $this->adaptiveBaseline->update($telemetry, $anomalies);

            $this->writeHealthReport($health, $anomalies, $telemetry);
            $stabilizationResults = $this->stabilization->checkPending();

            $outcome = [
                'outcome' => 'monitor',
                'health_score' => $health['overall_score'],
                'health_status' => $health['status'],
                'anomalies' => count($anomalies),
                'stabilization' => $stabilizationResults,
                'safe_mode' => $this->circuitBreaker->isOpen(),
            ];

            if ($anomalies !== []) {
                $rca = $this->rootCauseAnalyzer->analyze($telemetry, $anomalies);
                $this->writeRootCauseReport($rca, $anomalies);
                $outcome['root_cause_confidence'] = $this->confidenceFromRca($rca);

                if ($this->effectiveMode() === OptimizationMode::Recommend) {
                    $recommend = $this->optimizationEngine->recommend();
                    $outcome['recommend'] = $recommend;
                    $outcome['outcome'] = 'recommend';
                } elseif ($this->canAttemptSelfHeal($rca, $anomalies)) {
                    $heal = $this->attemptSelfHeal($rca, $telemetry, $anomalies[0]);
                    $outcome = array_merge($outcome, $heal);
                } else {
                    $outcome['outcome'] = 'degraded_observed';
                    $outcome['self_heal'] = 'skipped — safe mode, low confidence, cooldown, or observe mode';
                }
            }

            $this->stateStore->mutate(function (array $state) use ($outcome) {
                $state['last_cycle_at'] = now()->toIso8601String();
                $state['last_cycle_outcome'] = $outcome['outcome'];

                return $state;
            });

            return $outcome;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $anomalies
     */
    private function canAttemptSelfHeal(?RootCauseReport $rca, array $anomalies): bool
    {
        if ($this->effectiveMode() !== OptimizationMode::Autonomous) {
            return false;
        }

        if ($this->circuitBreaker->isOpen()) {
            return false;
        }

        if ($rca === null) {
            return false;
        }

        $minConfidence = (float) config('optimization.scoring.min_confidence_for_autonomous', 0.75);
        if ($this->confidenceFromRca($rca) < $minConfidence) {
            return false;
        }

        $target = $rca->affectedComponent;
        if ($this->cooldown->isOnCooldown($target)) {
            return false;
        }

        if ($this->targetLock->isLocked($target)) {
            return false;
        }

        return $anomalies !== [];
    }

    /**
     * @param  array<string, mixed>  $telemetry
     * @param  array<string, mixed>  $primaryAnomaly
     * @return array<string, mixed>
     */
    private function attemptSelfHeal(RootCauseReport $rca, array $telemetry, array $primaryAnomaly): array
    {
        $target = $rca->affectedComponent;

        if (! $this->targetLock->acquire($target)) {
            return ['outcome' => 'locked', 'target' => $target];
        }

        try {
            $checkpointId = $this->checkpoint->create([
                'target' => $target,
                'root_cause' => $rca->toArray(),
                'baseline_metrics' => $telemetry,
                'anomaly' => $primaryAnomaly,
            ]);

            $result = $this->optimizationEngine->runAutonomous();

            if (! ($result['executed'] ?? false)) {
                $this->rollback->handleFailedOptimization($result);
                $this->cooldown->startCooldown($target);

                return [
                    'outcome' => 'heal_failed',
                    'target' => $target,
                    'checkpoint_id' => $checkpointId,
                    'result' => $result,
                ];
            }

            $this->circuitBreaker->recordSuccess();
            $this->cooldown->startCooldown($target);

            $optimizationId = $result['history_id'] ?? $checkpointId;
            $this->stabilization->start($optimizationId, [
                'target' => $target,
                'checkpoint_id' => $checkpointId,
                'recommendation_id' => $result['checkpoint']['recommendation_id'] ?? null,
                'event_code' => $result['event_code'] ?? null,
            ]);

            $this->writeSelfHealingStatus('ACCEPTED', $target, $result);

            return [
                'outcome' => 'heal_applied',
                'target' => $target,
                'checkpoint_id' => $checkpointId,
                'result' => $result,
                'stabilization_started' => true,
            ];
        } finally {
            $this->targetLock->release($target);
        }
    }

    private function effectiveMode(): OptimizationMode
    {
        if ($this->circuitBreaker->isOpen()) {
            return OptimizationMode::Observe;
        }

        return OptimizationMode::tryFrom(config('optimization.mode', 'observe'))
            ?? OptimizationMode::Observe;
    }

    private function confidenceFromRca(?RootCauseReport $rca): float
    {
        if ($rca === null) {
            return 0.0;
        }

        $evidence = $rca->evidence;
        $score = 0.5;
        if (($evidence['consecutive_observations'] ?? 0) >= 3) {
            $score += 0.15;
        }
        if (($evidence['degradation_pct'] ?? 0) >= 20) {
            $score += 0.15;
        }
        if (($evidence['db_queries_per_request'] ?? 0) > 10) {
            $score += 0.1;
        }

        return min(0.99, $score);
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
    private function writeRootCauseReport(?RootCauseReport $rca, array $anomalies): void
    {
        if ($rca === null) {
            return;
        }

        $content = "# Root Cause Report\n\nGenerated: ".now()->toIso8601String()."\n\n";
        $content .= json_encode($rca->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
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
