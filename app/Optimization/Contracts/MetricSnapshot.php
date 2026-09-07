<?php

namespace App\Optimization\Contracts;

/**
 * Immutable metric snapshot — use null for unavailable metrics (never fake zero).
 */
final readonly class MetricSnapshot
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $capturedAt,
        public ?float $cpuPct = null,
        public ?float $memoryMb = null,
        public ?float $memoryPct = null,
        public ?float $p95LatencyMs = null,
        public ?float $p99LatencyMs = null,
        public ?float $queryCount = null,
        public ?float $cacheHitRatio = null,
        public ?float $errorRatePct = null,
        public ?float $connectionCount = null,
        public ?float $databaseSizeMb = null,
        public ?float $queueDepth = null,
        public ?float $queueLatencyMs = null,
        public ?float $diskIo = null,
        public ?float $networkIo = null,
        public ?float $healthScore = null,
        public array $context = [],
    ) {}

    /**
     * @return array<string, float|null>
     */
    public function toGuardArray(): array
    {
        return [
            'p95_latency_ms' => $this->p95LatencyMs,
            'p99_latency_ms' => $this->p99LatencyMs,
            'cpu_pct' => $this->cpuPct,
            'memory_mb' => $this->memoryMb,
            'db_queries_per_request' => $this->queryCount,
            'cache_hit_ratio' => $this->cacheHitRatio,
            'error_rate_pct' => $this->errorRatePct,
        ];
    }

    /**
     * @return list<string>
     */
    public function unavailableCriticalMetrics(): array
    {
        $missing = [];
        foreach (['p95_latency_ms', 'cache_hit_ratio'] as $metric) {
            if ($this->toGuardArray()[$metric] === null) {
                $missing[] = $metric;
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            capturedAt: (string) ($data['captured_at'] ?? now()->toIso8601String()),
            cpuPct: isset($data['cpu_pct']) ? (float) $data['cpu_pct'] : null,
            memoryMb: isset($data['memory_mb']) ? (float) $data['memory_mb'] : null,
            memoryPct: isset($data['memory_pct']) ? (float) $data['memory_pct'] : null,
            p95LatencyMs: isset($data['p95_latency_ms']) ? (float) $data['p95_latency_ms'] : null,
            p99LatencyMs: isset($data['p99_latency_ms']) ? (float) $data['p99_latency_ms'] : null,
            queryCount: isset($data['query_count']) ? (float) $data['query_count'] : null,
            cacheHitRatio: isset($data['cache_hit_ratio']) ? (float) $data['cache_hit_ratio'] : null,
            errorRatePct: isset($data['error_rate_pct']) ? (float) $data['error_rate_pct'] : null,
            connectionCount: isset($data['connection_count']) ? (float) $data['connection_count'] : null,
            databaseSizeMb: isset($data['database_size_mb']) ? (float) $data['database_size_mb'] : null,
            queueDepth: isset($data['queue_depth']) ? (float) $data['queue_depth'] : null,
            queueLatencyMs: isset($data['queue_latency_ms']) ? (float) $data['queue_latency_ms'] : null,
            diskIo: isset($data['disk_io']) ? (float) $data['disk_io'] : null,
            networkIo: isset($data['network_io']) ? (float) $data['network_io'] : null,
            healthScore: isset($data['health_score']) ? (float) $data['health_score'] : null,
            context: (array) ($data['context'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'captured_at' => $this->capturedAt,
            'cpu_pct' => $this->cpuPct,
            'memory_mb' => $this->memoryMb,
            'memory_pct' => $this->memoryPct,
            'p95_latency_ms' => $this->p95LatencyMs,
            'p99_latency_ms' => $this->p99LatencyMs,
            'query_count' => $this->queryCount,
            'cache_hit_ratio' => $this->cacheHitRatio,
            'error_rate_pct' => $this->errorRatePct,
            'connection_count' => $this->connectionCount,
            'database_size_mb' => $this->databaseSizeMb,
            'queue_depth' => $this->queueDepth,
            'queue_latency_ms' => $this->queueLatencyMs,
            'disk_io' => $this->diskIo,
            'network_io' => $this->networkIo,
            'health_score' => $this->healthScore,
            'context' => $this->context,
        ];
    }
}
