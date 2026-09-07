<?php

namespace App\Optimization\SelfHealing;

use Carbon\Carbon;

final class StabilizationMonitor
{
    public function __construct(
        private readonly SelfHealingStateStore $stateStore,
        private readonly HealthScoreEngine $healthScore,
        private readonly AdaptiveBaselineEngine $baselineEngine,
        private readonly TelemetryCollector $telemetry,
        private readonly RollbackCoordinator $rollback,
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

            if ($health['status'] === 'unhealthy') {
                $this->rollback->rollbackFromStabilization($entry);
                unset($active[$id]);
                $results[] = ['id' => $id, 'outcome' => 'rollback', 'reason' => 'degradation during stabilization'];

                continue;
            }

            $minObs = (int) config('optimization.stabilization.min_observations', 5);
            $windowMinutes = (int) config('optimization.stabilization.window_minutes', 30);
            $startedAt = Carbon::parse($entry['started_at']);
            $windowElapsed = $startedAt->diffInMinutes(now()) >= $windowMinutes;

            if ($entry['observations'] >= $minObs && $windowElapsed) {
                unset($active[$id]);
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
    }
}
