<?php

namespace App\Optimization\SelfHealing;

use Carbon\Carbon;

final class OptimizationCooldownManager
{
    public function __construct(
        private readonly SelfHealingStateStore $stateStore,
    ) {}

    public function isOnCooldown(string $target): bool
    {
        $state = $this->stateStore->read();
        $cooldowns = $state['cooldowns'] ?? [];
        $until = $cooldowns[$target] ?? null;

        if ($until === null) {
            return false;
        }

        return now()->lt(Carbon::parse($until));
    }

    public function startCooldown(string $target): void
    {
        $minutes = (int) config('optimization.cooldown.minutes', 60);

        $this->stateStore->mutate(function (array $state) use ($target, $minutes) {
            $state['cooldowns'][$target] = now()->addMinutes($minutes)->toIso8601String();

            return $state;
        });
    }
}
