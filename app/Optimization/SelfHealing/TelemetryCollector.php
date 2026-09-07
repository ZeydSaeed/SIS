<?php

namespace App\Optimization\SelfHealing;

use App\Optimization\Telemetry\ErrorRateTelemetryProvider;
use App\Optimization\Telemetry\QueueTelemetryProvider;
use App\Intelligence\Models\MonitoringSnapshot;
use App\Intelligence\Models\QueryMetric;

class TelemetryCollector
{
    public function __construct(
        private readonly EnvironmentProfileService $environment,
        private readonly ErrorRateTelemetryProvider $errorRate,
        private readonly QueueTelemetryProvider $queueTelemetry,
    ) {}

    /**
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
        $cpuPct = $this->estimateCpuPct();

        $error = $this->errorRate->measure();
        $queue = $this->queueTelemetry->measure();

        return [
            'collected_at' => now()->toIso8601String(),
            'p95_latency_ms' => $p95Values->isNotEmpty() ? $p95Values->avg() : null,
            'p99_latency_ms' => $p99Values->isNotEmpty() ? $p99Values->avg() : null,
            'db_query_latency_note' => 'Query p95/p99 — not HTTP request latency',
            'cpu_pct' => $cpuPct > 0 ? $cpuPct : null,
            'cpu_status' => $cpuPct > 0 ? 'MEASURED' : 'UNKNOWN',
            'memory_mb' => round($memoryMb, 2),
            'memory_pct' => $this->normalizeMemory($memoryMb, $env),
            'db_queries_per_request' => $queryMetrics->isNotEmpty() ? (int) $queryMetrics->sum('call_count') : null,
            'cache_hit_ratio' => $health?->cache_hit_ratio !== null ? (float) $health->cache_hit_ratio : null,
            'error_rate_pct' => $error['value'],
            'error_rate_status' => $error['status'],
            'connection_count' => $health?->connection_count !== null ? (int) $health->connection_count : null,
            'database_size_mb' => $health?->database_size_mb !== null ? (float) $health->database_size_mb : null,
            'queue_depth' => $queue['depth'],
            'queue_latency_ms' => $queue['latency_ms'],
            'queue_status' => $queue['status'],
            'context_fingerprint' => [
                'environment' => config('app.env'),
                'cpu_cores' => $env['cpu_cores'] ?? null,
                'memory_limit_mb' => $env['memory_limit_mb'] ?? null,
                'php_version' => $env['php_version'] ?? PHP_VERSION,
                'database_size_mb' => $health?->database_size_mb,
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
    private function normalizeMemory(float $memoryMb, array $env): ?float
    {
        $limit = (float) ($env['memory_limit_mb'] ?? 0);
        if ($limit <= 0) {
            return null;
        }

        return round(($memoryMb / $limit) * 100, 2);
    }
}
