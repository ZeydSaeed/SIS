<?php

namespace App\Optimization\SelfHealing;

final class AnomalyDetector
{
    public function __construct(
        private readonly AdaptiveBaselineEngine $baselineEngine,
        private readonly SelfHealingStateStore $stateStore,
    ) {}

    /**
     * @param  array<string, mixed>  $telemetry
     * @param  array<string, mixed>  $healthScore
     * @return list<array<string, mixed>>
     */
    public function detect(array $telemetry, array $healthScore): array
    {
        $anomalies = [];
        $required = (int) config('optimization.anomaly.consecutive_observations_required', 3);
        $state = $this->stateStore->read();
        $streaks = $state['degradation_streaks'] ?? [];

        foreach ($healthScore['dimensions'] as $metric => $dimension) {
            if ($dimension['status'] !== 'degraded') {
                unset($streaks[$metric]);

                continue;
            }

            $streaks[$metric] = ($streaks[$metric] ?? 0) + 1;

            if ($streaks[$metric] < $required) {
                continue;
            }

            $anomalies[] = [
                'metric' => $metric,
                'domain' => $this->domainFor($metric),
                'component' => $metric,
                'degradation_pct' => $dimension['degradation_pct'],
                'current' => $dimension['current'],
                'baseline' => $dimension['baseline'],
                'consecutive_observations' => $streaks[$metric],
                'priority' => $dimension['degradation_pct'] >= 50 ? 'P0' : 'P1',
            ];
        }

        $this->stateStore->mutate(function (array $state) use ($streaks) {
            $state['degradation_streaks'] = $streaks;

            return $state;
        });

        return $anomalies;
    }

    private function domainFor(string $metric): string
    {
        return match (true) {
            str_contains($metric, 'latency') || str_contains($metric, 'db_queries') => 'database',
            str_contains($metric, 'cache') => 'cache',
            str_contains($metric, 'cpu') => 'cpu',
            str_contains($metric, 'memory') => 'memory',
            str_contains($metric, 'error') => 'errors',
            default => 'application',
        };
    }
}
