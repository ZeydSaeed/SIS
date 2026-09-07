<?php

namespace App\Optimization\SelfHealing;

use Carbon\Carbon;

/**
 * Conservative rate limits for controlled autonomous ANALYZE canary.
 */
final class AutonomousRateLimiter
{
    public function __construct(
        private readonly SelfHealingStateStore $stateStore,
        private readonly SelfHealingEventLogger $events,
    ) {}

    /**
     * @return array{allowed: bool, code: string, reason: ?string}
     */
    public function evaluate(string $normalizedTarget, string $cycleId): array
    {
        $maxPerCycle = (int) config('optimization.autonomous.rate_limits.max_per_cycle', 1);
        $maxPerTarget = (int) config('optimization.autonomous.rate_limits.max_per_target_per_window', 3);
        $maxGlobal = (int) config('optimization.autonomous.rate_limits.max_global_per_window', 10);
        $windowMinutes = (int) config('optimization.autonomous.rate_limits.window_minutes', 60);

        $state = $this->stateStore->read();
        $executions = $this->prune($state['autonomous_executions'] ?? [], $windowMinutes);
        $cycleExecutions = (int) ($state['autonomous_cycle_executions'][$cycleId] ?? 0);

        if ($cycleExecutions >= $maxPerCycle) {
            return $this->deny('rate_limit_cycle', "Maximum {$maxPerCycle} autonomous operation(s) per cycle exceeded");
        }

        $targetCount = count(array_filter(
            $executions,
            fn (array $entry) => ($entry['target'] ?? '') === $normalizedTarget,
        ));

        if ($targetCount >= $maxPerTarget) {
            return $this->deny(
                'rate_limit_target',
                "Maximum {$maxPerTarget} autonomous operation(s) per target in {$windowMinutes} minute window exceeded",
            );
        }

        if (count($executions) >= $maxGlobal) {
            return $this->deny(
                'rate_limit_global',
                "Maximum {$maxGlobal} autonomous operation(s) in {$windowMinutes} minute window exceeded",
            );
        }

        return ['allowed' => true, 'code' => 'eligible', 'reason' => null];
    }

    public function recordExecution(string $normalizedTarget, string $cycleId): void
    {
        $this->stateStore->mutate(function (array $state) use ($normalizedTarget, $cycleId) {
            $windowMinutes = (int) config('optimization.autonomous.rate_limits.window_minutes', 60);
            $executions = $this->prune($state['autonomous_executions'] ?? [], $windowMinutes);
            $executions[] = [
                'target' => $normalizedTarget,
                'cycle_id' => $cycleId,
                'at' => now()->toIso8601String(),
            ];
            $state['autonomous_executions'] = $executions;
            $state['autonomous_cycle_executions'][$cycleId] = ($state['autonomous_cycle_executions'][$cycleId] ?? 0) + 1;

            return $state;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $executions
     * @return list<array<string, mixed>>
     */
    private function prune(array $executions, int $windowMinutes): array
    {
        $cutoff = now()->subMinutes($windowMinutes);

        return array_values(array_filter($executions, function (array $entry) use ($cutoff) {
            $at = $entry['at'] ?? null;
            if ($at === null) {
                return false;
            }

            return Carbon::parse($at)->gte($cutoff);
        }));
    }

    /**
     * @return array{allowed: bool, code: string, reason: ?string}
     */
    private function deny(string $code, string $reason): array
    {
        $this->events->emit('AUTONOMOUS_RATE_LIMIT_DENIED', [
            'code' => $code,
            'reason' => $reason,
        ]);

        return ['allowed' => false, 'code' => $code, 'reason' => $reason];
    }
}
