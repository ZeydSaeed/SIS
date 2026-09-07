<?php

namespace App\Optimization\SelfHealing;

use App\Optimization\Analysis\BottleneckAnalyzer;
use App\Optimization\Contracts\IncidentReport;
use Illuminate\Support\Str;

final class RootCauseAnalyzer
{
    public function __construct(
        private readonly BottleneckAnalyzer $bottleneckAnalyzer,
    ) {}

    /**
     * @param  array<string, mixed>  $telemetry
     * @param  list<array<string, mixed>>  $anomalies
     */
    public function analyze(array $telemetry, array $anomalies): ?IncidentReport
    {
        if ($anomalies === []) {
            return null;
        }

        $primary = collect($anomalies)->sortByDesc('degradation_pct')->first();
        $domain = (string) ($primary['domain'] ?? 'application');
        $incidentId = 'INC-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));

        $bottleneck = $this->resolveDatabaseBottleneck($domain);

        $target = $bottleneck['target'] ?? (string) ($primary['component'] ?? $domain);
        $schemaName = $bottleneck['schema_name'] ?? null;
        $tableName = $bottleneck['table_name'] ?? null;
        $queryFingerprint = $bottleneck['query_fingerprint'] ?? null;

        $evidence = [
            'primary_metric' => $primary['metric'] ?? null,
            'degradation_pct' => $primary['degradation_pct'] ?? 0,
            'consecutive_observations' => $primary['consecutive_observations'] ?? 0,
            'p95_latency_ms' => $telemetry['p95_latency_ms'] ?? null,
            'db_queries_per_request' => $telemetry['db_queries_per_request'] ?? null,
            'cache_hit_ratio' => $telemetry['cache_hit_ratio'] ?? null,
            'cpu_pct' => $telemetry['cpu_pct'] ?? null,
            'memory_pct' => $telemetry['memory_pct'] ?? null,
        ];

        [$rootCause, $candidateActions, $confidence] = $this->inferRootCause($domain, $evidence, $bottleneck);

        return new IncidentReport(
            incidentId: $incidentId,
            timestamp: now()->toIso8601String(),
            primaryComponent: $domain,
            affectedComponent: $target,
            target: $target,
            rootCause: $rootCause,
            confidence: $confidence,
            evidence: $evidence,
            supportingMetrics: $telemetry,
            candidateActions: $candidateActions,
            risk: 'low',
            severity: (string) ($primary['priority'] ?? 'P1'),
            schemaName: $schemaName,
            tableName: $tableName,
            queryFingerprint: $queryFingerprint,
        );
    }

    /**
     * @return array{target: ?string, schema_name: ?string, table_name: ?string, query_fingerprint: ?string}
     */
    private function resolveDatabaseBottleneck(string $domain): array
    {
        if ($domain !== 'database') {
            return ['target' => null, 'schema_name' => null, 'table_name' => null, 'query_fingerprint' => null];
        }

        $bottlenecks = $this->bottleneckAnalyzer->analyze();
        $top = $bottlenecks[0] ?? null;
        if ($top === null) {
            return ['target' => null, 'schema_name' => null, 'table_name' => null, 'query_fingerprint' => null];
        }

        $component = (string) $top['component'];
        $parts = str_contains($component, '.') ? explode('.', $component, 2) : [null, $component];

        return [
            'target' => $component,
            'schema_name' => $parts[0],
            'table_name' => $parts[1] ?? $component,
            'query_fingerprint' => $top['evidence']['query_fingerprint'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @param  array<string, mixed>  $bottleneck
     * @return array{0: string, 1: list<string>, 2: float}
     */
    private function inferRootCause(string $domain, array $evidence, array $bottleneck): array
    {
        if ($domain === 'database') {
            $querySpike = ($evidence['db_queries_per_request'] ?? 0) > 10;
            if ($querySpike) {
                return ['query_frequency_regression', ['analyze'], 0.87];
            }

            return ['stale_planner_statistics_or_missing_index', ['analyze'], 0.78];
        }

        if ($domain === 'cache') {
            return ['cache_hit_ratio_degradation', [], 0.72];
        }

        if ($domain === 'cpu') {
            return ['cpu_pressure_computational_hotspot', [], 0.65];
        }

        if ($domain === 'memory') {
            return ['memory_pressure_allocation_growth', [], 0.68];
        }

        return ['unlocalized_application_degradation', ['analyze'], 0.55];
    }
}
