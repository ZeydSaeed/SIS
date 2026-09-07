<?php

namespace App\Optimization\SelfHealing;

use App\Intelligence\Models\MonitoringSnapshot;
use App\Intelligence\Models\QueryMetric;

final class TelemetryCollector
{
    public function __construct(
        private readonly EnvironmentProfileService $environment,
    ) {}

    /**
     * Lightweight telemetry — no expensive profiling.
     *
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $health = MonitoringSnapshot::query()->orderByDesc('captured_at')->first();
        $queryMetrics = QueryMetric::query()
            ->where('captured_at', '>=', now()->subMinutes(15))
            ->get();

        $p95Values = $queryMetrics->pluck('p95_ms')->filter()->map(fn ($v) => (float) $v);
        $p99Values = $queryMetrics->pluck('p99_ms')->filter()->map(fn ($v) => (float) $v);

        $memoryMb = memory_get_usage(true) / 1024 / 1024;
        $env = $this->environment->current();

        return [
            'collected_at' => now()->toIso8601String(),
            'p95_latency_ms' => $p95Values->avg() ?? 0,
            'p99_latency_ms' => $p99Values->avg() ?? 0,
            'cpu_pct' => $this->estimateCpuPct(),
            'memory_mb' => round($memoryMb, 2),
            'memory_pct' => $this->normalizeMemory($memoryMb, $env),
            'db_queries_per_request' => (int) $queryMetrics->sum('call_count'),
            'cache_hit_ratio' => (float) ($health?->cache_hit_ratio ?? 0),
            'error_rate_pct' => 0.0,
            'connection_count' => (int) ($health?->connection_count ?? 0),
            'database_size_mb' => (float) ($health?->database_size_mb ?? 0),
            'context_fingerprint' => [
                'environment' => config('app.env'),
                'cpu_cores' => $env['cpu_cores'] ?? 1,
                'memory_limit_mb' => $env['memory_limit_mb'] ?? null,
            ],
        ];
    }

    private function estimateCpuPct(): float
    {
        if (! function_exists('sys_getloadavg')) {
            return 0.0;
        }

        $load = sys_getloadavg();
        $cores = max(1, (int) (getenv('NUMBER_OF_PROCESSORS') ?: 1));

        return round(min(100, (($load[0] ?? 0) / $cores) * 100), 2);
    }

    /**
     * @param  array<string, mixed>  $env
     */
    private function normalizeMemory(float $memoryMb, array $env): float
    {
        $limit = (float) ($env['memory_limit_mb'] ?? 0);
        if ($limit <= 0) {
            return 0.0;
        }

        return round(($memoryMb / $limit) * 100, 2);
    }
}
