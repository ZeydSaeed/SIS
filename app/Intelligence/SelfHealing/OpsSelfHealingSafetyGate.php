<?php

namespace App\Intelligence\SelfHealing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Independent safety gate for Ops Tier-1 self-healing (replica routing, connection signals).
 * Separate from Optimization performance self-healing pipeline.
 */
final class OpsSelfHealingSafetyGate
{
    private const STATE_KEY = 'intelligence:ops-self-healing:state';

    /**
     * @param  array<string, mixed>  $context
     * @return array{allowed: bool, reason: ?string}
     */
    public function authorize(string $playbookId, array $context = []): array
    {
        if (! config('intelligence.ops_self_healing.enabled', true)) {
            return ['allowed' => false, 'reason' => 'Ops self-healing disabled'];
        }

        $allowlist = config('intelligence.ops_self_healing.allowlist', [
            'replica_lag',
            'connection_saturation',
        ]);

        if (! in_array($playbookId, $allowlist, true)) {
            return ['allowed' => false, 'reason' => 'Playbook not in ops allowlist'];
        }

        if ($this->isCircuitOpen()) {
            return ['allowed' => false, 'reason' => 'Ops circuit breaker open'];
        }

        $cooldownKey = "intelligence:ops-self-healing:cooldown:{$playbookId}";
        if (Cache::has($cooldownKey)) {
            return ['allowed' => false, 'reason' => "Ops cooldown active for {$playbookId}"];
        }

        return ['allowed' => true, 'reason' => null];
    }

    public function recordExecution(string $playbookId): void
    {
        $cooldownMinutes = (int) config('intelligence.ops_self_healing.cooldown_minutes', 15);
        Cache::put("intelligence:ops-self-healing:cooldown:{$playbookId}", now()->toIso8601String(), now()->addMinutes($cooldownMinutes));

        Log::info('OPS_SELF_HEALING:ACTION_EXECUTED', [
            'playbook_id' => $playbookId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function recordFailure(string $reason): void
    {
        $state = Cache::get(self::STATE_KEY, ['failures' => 0]);
        $state['failures'] = ($state['failures'] ?? 0) + 1;
        $state['last_failure'] = $reason;
        $state['last_failure_at'] = now()->toIso8601String();

        $max = (int) config('intelligence.ops_self_healing.max_failures', 5);
        if ($state['failures'] >= $max) {
            $state['circuit_open'] = true;
            $state['circuit_opened_at'] = now()->toIso8601String();
            Log::warning('OPS_SELF_HEALING:CIRCUIT_BREAKER_OPENED', $state);
        }

        Cache::put(self::STATE_KEY, $state, now()->addDay());
    }

    public function isCircuitOpen(): bool
    {
        $state = Cache::get(self::STATE_KEY, []);

        return ($state['circuit_open'] ?? false) === true;
    }

    public function resetCircuit(): void
    {
        Cache::forget(self::STATE_KEY);
    }
}
