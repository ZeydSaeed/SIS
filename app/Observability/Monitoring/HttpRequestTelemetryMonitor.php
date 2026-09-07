<?php

namespace App\Observability\Monitoring;

use App\Intelligence\Models\MonitoringSnapshot;
use App\Intelligence\Support\CorrelationContext;
use Illuminate\Support\Collection;

final class HttpRequestTelemetryMonitor
{
    /** @var array<string, list<array{duration_ms: float, db_queries: int, status_code: int, route: ?string}>> */
    private static array $samplesByWorkload = [];

    /**
     * @param  array{
     *     workload: string,
     *     route: ?string,
     *     method: ?string,
     *     path: ?string,
     *     duration_ms: float,
     *     db_queries: int,
     *     status_code: int
     * }  $sample
     */
    public function record(array $sample): void
    {
        self::$samplesByWorkload[$sample['workload']][] = [
            'duration_ms' => $sample['duration_ms'],
            'db_queries' => $sample['db_queries'],
            'status_code' => $sample['status_code'],
            'route' => $sample['route'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function aggregateWorkload(string $workload): ?array
    {
        $samples = self::$samplesByWorkload[$workload] ?? [];

        if ($samples === []) {
            return null;
        }

        return $this->buildMetrics($workload, $samples);
    }

    /**
     * @return Collection<int, MonitoringSnapshot>
     */
    public function flush(): Collection
    {
        $snapshots = collect();

        foreach (self::$samplesByWorkload as $workload => $samples) {
            if ($samples === []) {
                continue;
            }

            $metrics = $this->buildMetrics($workload, $samples);

            $snapshots->push(MonitoringSnapshot::query()->create([
                'snapshot_type' => 'http_workload',
                'metrics' => $metrics,
                'correlation_id' => CorrelationContext::id(),
                'captured_at' => now(),
            ]));
        }

        self::$samplesByWorkload = [];

        return $snapshots;
    }

    /**
     * @param  list<array{duration_ms: float, db_queries: int, status_code: int, route: ?string}>  $samples
     * @return array<string, mixed>
     */
    private function buildMetrics(string $workload, array $samples): array
    {
        $durations = array_column($samples, 'duration_ms');
        sort($durations);
        $count = count($durations);
        $p50Index = (int) floor(max(0, ($count - 1) * 0.50));
        $p95Index = (int) floor(max(0, ($count - 1) * 0.95));
        $p99Index = (int) floor(max(0, ($count - 1) * 0.99));

        $errors = count(array_filter($samples, fn (array $sample): bool => $sample['status_code'] >= 500));
        $dbQueries = array_column($samples, 'db_queries');
        $routes = array_values(array_filter(array_unique(array_column($samples, 'route'))));

        $budgetMs = config("intelligence.performance_budgets.{$workload}.p95_ms");
        $p95 = $durations[$p95Index];

        return [
            'workload' => $workload,
            'routes' => $routes,
            'sample_count' => $count,
            'p50_ms' => $durations[$p50Index],
            'p95_ms' => $p95,
            'p99_ms' => $durations[$p99Index],
            'mean_ms' => round(array_sum($durations) / $count, 2),
            'mean_db_queries_per_request' => round(array_sum($dbQueries) / $count, 2),
            'error_count' => $errors,
            'error_rate_pct' => round(($errors / $count) * 100, 2),
            'performance_budget_p95_ms' => $budgetMs,
            'budget_exceeded' => is_numeric($budgetMs) ? $p95 > (float) $budgetMs : null,
            'source' => 'http_request_telemetry',
        ];
    }

    public static function resetSamples(): void
    {
        self::$samplesByWorkload = [];
    }
}
