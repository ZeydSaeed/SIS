<?php

namespace App\Intelligence\SelfHealing;

use App\Intelligence\Enums\RiskTier;
use App\Intelligence\Models\MonitoringSnapshot;
use App\Intelligence\Models\SelfHealingAction;
use App\Intelligence\Support\CorrelationContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ops Tier-1 self-healing — infrastructure playbooks (NOT optimization performance actions).
 * Governed by OpsSelfHealingSafetyGate.
 */
class SelfHealingEngine
{
    public function __construct(
        private readonly OpsSelfHealingSafetyGate $safetyGate,
    ) {}

    public function evaluate(MonitoringSnapshot $snapshot): ?SelfHealingAction
    {
        if (! config('intelligence.self_healing.enabled', true)) {
            return null;
        }

        if ($this->shouldRouteReadsToPrimary($snapshot)) {
            return $this->executePlaybook(
                'replica_lag',
                'Route eligible reads to primary — replica lag exceeded threshold',
                ['replication_lag_seconds' => $snapshot->replication_lag_seconds],
                fn () => Cache::put('intelligence.read_from_replica', false, now()->addMinutes(15))
            );
        }

        if ($this->shouldSignalConnectionSaturation($snapshot)) {
            return $this->executePlaybook(
                'connection_saturation',
                'Connection saturation detected — alert ops and extend safe cache TTL',
                [
                    'connection_count' => $snapshot->connection_count,
                    'threshold_pct' => config('intelligence.self_healing.connection_saturation_threshold_pct'),
                ],
                fn () => Cache::put('intelligence.connection_saturation', true, now()->addMinutes(10))
            );
        }

        return null;
    }

    private function shouldRouteReadsToPrimary(MonitoringSnapshot $snapshot): bool
    {
        $threshold = (int) config('intelligence.self_healing.replica_lag_threshold_seconds', 30);

        return $snapshot->replication_lag_seconds !== null
            && (float) $snapshot->replication_lag_seconds > $threshold
            && Cache::get('intelligence.read_from_replica', true) === true;
    }

    private function shouldSignalConnectionSaturation(MonitoringSnapshot $snapshot): bool
    {
        return $snapshot->connection_count !== null
            && $snapshot->connection_count > 0
            && ! Cache::has('intelligence.connection_saturation');
    }

    /**
     * @param  array<string, mixed>  $metricsBefore
     */
    private function executePlaybook(string $playbookId, string $reason, array $metricsBefore, callable $action): ?SelfHealingAction
    {
        $auth = $this->safetyGate->authorize($playbookId, $metricsBefore);
        if (! ($auth['allowed'] ?? false)) {
            Log::info('OPS_SELF_HEALING:ACTION_REJECTED', [
                'playbook_id' => $playbookId,
                'reason' => $auth['reason'] ?? 'denied',
            ]);

            return null;
        }

        try {
            $action();
            $this->safetyGate->recordExecution($playbookId);
        } catch (\Throwable $e) {
            $this->safetyGate->recordFailure($e->getMessage());

            return null;
        }

        return SelfHealingAction::query()->create([
            'action_code' => 'SH-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'playbook_id' => $playbookId,
            'risk_tier' => RiskTier::SafeAuto->value,
            'trigger_reason' => $reason,
            'action_taken' => $playbookId,
            'metrics_before' => $metricsBefore,
            'metrics_after' => null,
            'outcome' => 'success',
            'auto_executed' => true,
            'correlation_id' => CorrelationContext::id(),
            'executed_at' => now(),
            'verified_at' => now(),
        ]);
    }
}
