<?php

namespace App\Optimization\SelfHealing;

final class CircuitBreaker
{
    public function __construct(
        private readonly SelfHealingStateStore $stateStore,
        private readonly SelfHealingEventLogger $events,
    ) {}

    public function isOpen(): bool
    {
        return $this->stateStore->isSafeMode();
    }

    public function recordFailure(string $reason): void
    {
        $max = (int) config('optimization.circuit_breaker.max_failed_attempts', 3);
        $opened = false;

        $this->stateStore->mutate(function (array $state) use ($reason, $max, &$opened) {
            $state['consecutive_failures'] = ($state['consecutive_failures'] ?? 0) + 1;
            $state['last_failure_reason'] = $reason;
            $state['last_failure_at'] = now()->toIso8601String();

            if ($state['consecutive_failures'] >= $max) {
                $state['safe_mode'] = true;
                $state['safe_mode_reason'] = "Circuit breaker: {$max} consecutive failures";
                $state['safe_mode_entered_at'] = now()->toIso8601String();
                $opened = true;
            }

            return $state;
        });

        if ($opened) {
            $this->events->emit('CIRCUIT_BREAKER_OPENED', ['reason' => $reason]);
        }
    }

    public function recordSuccess(): void
    {
        $this->stateStore->mutate(function (array $state) {
            $state['consecutive_failures'] = 0;
            $state['last_success_at'] = now()->toIso8601String();

            return $state;
        });
    }

    public function reset(): void
    {
        $wasOpen = $this->isOpen();
        $this->stateStore->exitSafeMode();

        if ($wasOpen) {
            $this->events->emit('CIRCUIT_BREAKER_CLOSED', ['reason' => 'manual_reset']);
        }
    }
}
