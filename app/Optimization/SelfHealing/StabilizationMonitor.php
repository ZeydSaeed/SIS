<?php

namespace App\Optimization\SelfHealing;

use App\Optimization\Gates\CrossMetricGuard;
use Carbon\Carbon;

final class StabilizationMonitor
{
    public function __construct(
        private readonly SelfHealingStateStore $stateStore,
        private readonly HealthScoreEngine $healthScore,
        private readonly AdaptiveBaselineEngine $baselineEngine,
        private readonly TelemetryCollector $telemetry,
        private readonly RollbackCoordinator $rollback,
        private readonly CrossMetricGuard $crossMetricGuard,
        private readonly SelfHealingEventLogger $events,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function checkPending(): array
    {
        $state = $this->stateStore->read();
        $active = $state['active_stabilizations'] ?? [];
        $results = [];

        foreach ($active as $id => $entry) {
            $telemetry = $this->telemetry->collect();
            $baseline = $this->baselineEngine->load();
            $health = $this->healthScore->evaluate($telemetry, $baseline);

            $entry['observations'] = ($entry['observations'] ?? 0) + 1;
            $entry['last_health_score'] = $health['overall_score'];

            $beforeMetrics = $entry['before_metrics'] ?? null;
            if (is_array($beforeMetrics)) {
                $afterGuard = $this->metricCapturerGuardArray($telemetry, $health);
                $guardResult = $this->crossMetricGuard->evaluate(
                    $this->extractGuardMetrics($beforeMetrics),
                    $afterGuard,
                );

                if (! $guardResult['passed']) {
                    $this->events->emit('STABILIZATION_FAILED', [
                        'id' => $id,
                        'regressions' => $guardResult['regressions'],
                    ]);
                    $this->rollback->rollbackFromStabilization($entry);
                    unset($active[$id]);
                    $results[] = [
                        'id' => $id,
                        'outcome' => 'rollback',
                        'reason' => 'cross-metric regression during stabilization',
                        'regressions' => $guardResult['regressions'],
                    ];

                    continue;
                }
            }

            if ($health['status'] === 'unhealthy') {
                $this->events->emit('STABILIZATION_FAILED', ['id' => $id, 'reason' => 'health_unhealthy']);
                $this->rollback->rollbackFromStabilization($entry);
                unset($active[$id]);
                $results[] = ['id' => $id, 'outcome' => 'rollback', 'reason' => 'degradation during stabilization'];

                continue;
            }

            $minObs = (int) config('optimization.stabilization.min_observations', 5);
            $windowMinutes = (int) config('optimization.stabilization.window_minutes', 30);
            $maxWindowMinutes = (int) config('optimization.stabilization.max_window_minutes', 120);
            $startedAt = Carbon::parse($entry['started_at']);
            $elapsedMinutes = $startedAt->diffInMinutes(now());
            $windowElapsed = $elapsedMinutes >= $windowMinutes;
            $maxWindowExceeded = $elapsedMinutes >= $maxWindowMinutes;

            if (($entry['observations'] >= $minObs && $windowElapsed) || $maxWindowExceeded) {
                unset($active[$id]);
                $this->events->emit('OPTIMIZATION_ACCEPTED', ['id' => $id]);
                $results[] = ['id' => $id, 'outcome' => 'stable'];
            } else {
                $active[$id] = $entry;
            }
        }

        $this->stateStore->mutate(function (array $state) use ($active) {
            $state['active_stabilizations'] = $active;

            return $state;
        });

        return $results;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function start(string $optimizationId, array $context): void
    {
        $this->stateStore->mutate(function (array $state) use ($optimizationId, $context) {
            $state['active_stabilizations'][$optimizationId] = array_merge($context, [
                'started_at' => now()->toIso8601String(),
                'observations' => 0,
            ]);

            return $state;
        });

        $this->events->emit('STABILIZATION_STARTED', ['optimization_id' => $optimizationId]);
    }

    /**
     * @param  array<string, mixed>  $beforeMetrics
     * @return array<string, float|null>
     */
    private function extractGuardMetrics(array $beforeMetrics): array
    {
        return [
            'p95_latency_ms' => $beforeMetrics['p95_latency_ms'] ?? null,
            'p99_latency_ms' => $beforeMetrics['p99_latency_ms'] ?? null,
            'cpu_pct' => $beforeMetrics['cpu_pct'] ?? null,
            'memory_mb' => $beforeMetrics['memory_mb'] ?? null,
            'db_queries_per_request' => $beforeMetrics['query_count'] ?? null,
            'cache_hit_ratio' => $beforeMetrics['cache_hit_ratio'] ?? null,
            'error_rate_pct' => $beforeMetrics['error_rate_pct'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $telemetry
     * @param  array<string, mixed>  $health
     * @return array<string, float|null>
     */
    private function metricCapturerGuardArray(array $telemetry, array $health): array
    {
        return [
            'p95_latency_ms' => isset($telemetry['p95_latency_ms']) ? (float) $telemetry['p95_latency_ms'] : null,
            'p99_latency_ms' => isset($telemetry['p99_latency_ms']) ? (float) $telemetry['p99_latency_ms'] : null,
            'cpu_pct' => isset($telemetry['cpu_pct']) ? (float) $telemetry['cpu_pct'] : null,
            'memory_mb' => isset($telemetry['memory_mb']) ? (float) $telemetry['memory_mb'] : null,
            'db_queries_per_request' => isset($telemetry['db_queries_per_request']) ? (float) $telemetry['db_queries_per_request'] : null,
            'cache_hit_ratio' => isset($telemetry['cache_hit_ratio']) ? (float) $telemetry['cache_hit_ratio'] : null,
            'error_rate_pct' => isset($telemetry['error_rate_pct']) ? (float) $telemetry['error_rate_pct'] : null,
        ];
    }
}
