<?php

namespace App\Optimization\SelfHealing;

use App\Optimization\Contracts\MetricSnapshot;

class MetricSnapshotCapturer
{
    public function __construct(
        private readonly TelemetryCollector $telemetry,
        private readonly HealthScoreEngine $healthScore,
        private readonly AdaptiveBaselineEngine $baselineEngine,
    ) {}

    public function capture(): MetricSnapshot
    {
        $telemetry = $this->telemetry->collect();
        $baseline = $this->baselineEngine->load();
        $health = $this->healthScore->evaluate($telemetry, $baseline);

        return new MetricSnapshot(
            capturedAt: (string) ($telemetry['collected_at'] ?? now()->toIso8601String()),
            cpuPct: $this->nullableFloat($telemetry['cpu_pct'] ?? null),
            memoryMb: $this->nullableFloat($telemetry['memory_mb'] ?? null),
            memoryPct: $this->nullableFloat($telemetry['memory_pct'] ?? null),
            p95LatencyMs: $this->nullableFloat($telemetry['p95_latency_ms'] ?? null),
            p99LatencyMs: $this->nullableFloat($telemetry['p99_latency_ms'] ?? null),
            queryCount: $this->nullableFloat($telemetry['db_queries_per_request'] ?? null),
            cacheHitRatio: $this->nullableFloat($telemetry['cache_hit_ratio'] ?? null),
            errorRatePct: array_key_exists('error_rate_pct', $telemetry) && $telemetry['error_rate_pct'] !== null
                ? $this->nullableFloat($telemetry['error_rate_pct'])
                : null,
            connectionCount: $this->nullableFloat($telemetry['connection_count'] ?? null),
            databaseSizeMb: $this->nullableFloat($telemetry['database_size_mb'] ?? null),
            queueDepth: $this->nullableFloat($telemetry['queue_depth'] ?? null),
            queueLatencyMs: $this->nullableFloat($telemetry['queue_latency_ms'] ?? null),
            healthScore: $this->nullableFloat($health['overall_score'] ?? null),
            context: (array) ($telemetry['context_fingerprint'] ?? []),
        );
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return (float) $value;
    }
}
