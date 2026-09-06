<?php

namespace App\Intelligence\Optimization;

use App\Intelligence\Enums\OptimizationOutcome;
use App\Intelligence\Enums\RecommendationStatus;
use App\Intelligence\Models\OptimizationEvent;
use App\Intelligence\Models\QueryMetric;
use Illuminate\Support\Facades\Log;

class VerificationService
{
    public function verifyOptimizationEvent(OptimizationEvent $event): OptimizationEvent
    {
        $beforeP95 = (float) ($event->trigger['query_p95_ms'] ?? 0);
        $afterP95 = $this->latestP95ForTable($event->schema_name, $event->table_name);

        if ($beforeP95 <= 0 || $afterP95 === null) {
            $event->update([
                'outcome' => OptimizationOutcome::Partial->value,
                'results' => ['note' => 'Insufficient metrics for full verification'],
                'verified_at' => now(),
            ]);

            return $event->fresh();
        }

        $improvementPct = (($beforeP95 - $afterP95) / $beforeP95) * 100;
        $successThreshold = (float) config('intelligence.verification.success_p95_reduction_pct', 30);
        $regressionThreshold = (float) config('intelligence.verification.regression_p95_increase_pct', 20);

        if ($improvementPct >= $successThreshold) {
            $outcome = OptimizationOutcome::Success;
        } elseif ($improvementPct <= -$regressionThreshold) {
            $outcome = OptimizationOutcome::RolledBack;
            $event->recommendation?->update(['status' => RecommendationStatus::RolledBack->value]);
            Log::warning('Intelligence: optimization regression detected', [
                'event_code' => $event->event_code,
                'improvement_pct' => $improvementPct,
            ]);
        } elseif ($improvementPct > 0) {
            $outcome = OptimizationOutcome::Partial;
        } else {
            $outcome = OptimizationOutcome::Failure;
        }

        $event->update([
            'outcome' => $outcome->value,
            'results' => [
                'p95_before_ms' => $beforeP95,
                'p95_after_ms' => $afterP95,
                'improvement_pct' => round($improvementPct, 2),
            ],
            'verified_at' => now(),
        ]);

        return $event->fresh();
    }

    private function latestP95ForTable(?string $schema, ?string $table): ?float
    {
        if ($schema === null || $table === null) {
            return null;
        }

        $metric = QueryMetric::query()->orderByDesc('captured_at')->first();

        return $metric ? (float) $metric->p95_ms : null;
    }
}
