<?php

namespace App\Optimization\SelfHealing;

use App\Optimization\Analysis\RootCauseReport;

final class RootCauseAnalyzer
{
    /**
     * @param  array<string, mixed>  $telemetry
     * @param  list<array<string, mixed>>  $anomalies
     */
    public function analyze(array $telemetry, array $anomalies): ?RootCauseReport
    {
        if ($anomalies === []) {
            return null;
        }

        $primary = collect($anomalies)->sortByDesc('degradation_pct')->first();
        $domain = (string) ($primary['domain'] ?? 'application');

        $evidence = [
            'primary_metric' => $primary['metric'],
            'degradation_pct' => $primary['degradation_pct'],
            'consecutive_observations' => $primary['consecutive_observations'],
            'p95_latency_ms' => $telemetry['p95_latency_ms'] ?? 0,
            'db_queries_per_request' => $telemetry['db_queries_per_request'] ?? 0,
            'cache_hit_ratio' => $telemetry['cache_hit_ratio'] ?? 0,
            'cpu_pct' => $telemetry['cpu_pct'] ?? 0,
            'memory_pct' => $telemetry['memory_pct'] ?? 0,
        ];

        [$rootCause, $candidate, $confidence] = $this->inferRootCause($domain, $evidence);

        return new RootCauseReport(
            problem: "Performance degradation in {$domain}",
            evidence: $evidence,
            rootCause: $rootCause,
            affectedComponent: (string) ($primary['component'] ?? $domain),
            optimizationCandidate: $candidate,
            expectedImprovementPct: 20.0,
            riskTier: 1,
            validationMethod: 'VerifyOptimizationJob + CrossMetricGuard',
            rollbackStrategy: 'Mark recommendation rolled back; ANALYZE requires no schema rollback',
            potentialSideEffects: $this->potentialSideEffects($domain),
        );
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array{0: string, 1: string, 2: float}
     */
    private function inferRootCause(string $domain, array $evidence): array
    {
        if ($domain === 'database') {
            $querySpike = ($evidence['db_queries_per_request'] ?? 0) > 10;
            if ($querySpike) {
                return ['N+1 or query frequency regression', 'analyze', 0.85];
            }

            return ['Stale planner statistics or missing index', 'analyze', 0.78];
        }

        if ($domain === 'cache') {
            return ['Cache hit ratio degradation — invalidation or TTL issue', 'cache_ttl_adjust', 0.72];
        }

        if ($domain === 'cpu') {
            return ['CPU pressure — computational hotspot', 'remove_redundant_computation', 0.65];
        }

        if ($domain === 'memory') {
            return ['Memory pressure — allocation growth or cache expansion', 'cache_ttl_adjust', 0.68];
        }

        return ['Unlocalized application degradation', 'analyze', 0.55];
    }

    /**
     * @return list<string>
     */
    private function potentialSideEffects(string $domain): array
    {
        return match ($domain) {
            'database' => ['Increased CPU during ANALYZE', 'Temporary lock contention'],
            'cache' => ['Temporary cache miss spike during TTL change'],
            default => ['Cross-metric regression possible'],
        };
    }
}
