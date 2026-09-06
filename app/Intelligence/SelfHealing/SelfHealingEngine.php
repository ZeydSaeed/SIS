<?php

namespace App\Intelligence\SelfHealing;

use App\Intelligence\Enums\RiskTier;
use App\Intelligence\Models\MonitoringSnapshot;
use App\Intelligence\Models\SelfHealingAction;
use App\Intelligence\Support\CorrelationContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SelfHealingEngine
{
    public function evaluate(MonitoringSnapshot $snapshot): ?SelfHealingAction
    {
        if (! config('intelligence.self_healing.enabled', true)) {
            return null;
        }

        if ($this->shouldRouteReadsToPrimary($snapshot)) {
            return $this->recordAction(
                'replica_lag',
                'Route eligible reads to primary — replica lag exceeded threshold',
                ['replication_lag_seconds' => $snapshot->replication_lag_seconds],
                fn () => Cache::put('intelligence.read_from_replica', false, now()->addMinutes(15))
            );
        }

        if ($this->shouldSignalConnectionSaturation($snapshot)) {
            return $this->recordAction(
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
        $maxConnections = (int) config('intelligence.self_healing.max_connections_pct', 85);

        return $snapshot->connection_count !== null
            && $snapshot->connection_count > 0
            && ! Cache::has('intelligence.connection_saturation');
    }

    /**
     * @param  array<string, mixed>  $metricsBefore
     */
    private function recordAction(string $playbookId, string $reason, array $metricsBefore, callable $action): SelfHealingAction
    {
        $action();

        Log::info('Intelligence self-healing action executed', [
            'playbook' => $playbookId,
            'reason' => $reason,
        ]);

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
