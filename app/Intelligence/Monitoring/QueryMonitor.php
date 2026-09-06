<?php

namespace App\Intelligence\Monitoring;

use App\Intelligence\Models\QueryMetric;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class QueryMonitor
{
    /** @var array<string, list<float>> */
    private static array $runtimeSamples = [];

    public function recordRuntimeSample(string $fingerprint, float $durationMs, ?string $label = null): void
    {
        self::$runtimeSamples[$fingerprint]['durations'][] = $durationMs;
        self::$runtimeSamples[$fingerprint]['label'] = $label ?? self::$runtimeSamples[$fingerprint]['label'] ?? null;
    }

    /**
     * @return Collection<int, QueryMetric>
     */
    public function flushRuntimeSamples(): Collection
    {
        $metrics = collect();

        foreach (self::$runtimeSamples as $fingerprint => $data) {
            $durations = $data['durations'] ?? [];
            if ($durations === []) {
                continue;
            }

            sort($durations);
            $count = count($durations);
            $p95Index = (int) floor(max(0, ($count - 1) * 0.95));
            $p50Index = (int) floor(max(0, ($count - 1) * 0.50));
            $p99Index = (int) floor(max(0, ($count - 1) * 0.99));

            $p95 = $durations[$p95Index];
            $baselineKey = "intelligence.baseline.p95.{$fingerprint}";
            $baseline = Cache::get($baselineKey, $p95);

            if (! Cache::has($baselineKey)) {
                Cache::forever($baselineKey, $p95);
            }

            $degradation = $baseline > 0
                ? round((($p95 - $baseline) / $baseline) * 100, 2)
                : 0;

            $metrics->push(QueryMetric::query()->create([
                'query_fingerprint' => $fingerprint,
                'query_label' => $data['label'] ?? null,
                'call_count' => $count,
                'p50_ms' => $durations[$p50Index],
                'p95_ms' => $p95,
                'p99_ms' => $durations[$p99Index],
                'mean_ms' => round(array_sum($durations) / $count, 2),
                'baseline_p95_ms' => $baseline,
                'degradation_pct' => $degradation,
                'workload_class' => $this->inferWorkloadClass($count, $p95),
                'context' => ['source' => 'runtime_listener'],
                'captured_at' => now(),
            ]));
        }

        self::$runtimeSamples = [];

        return $metrics;
    }

    public static function resetRuntimeSamples(): void
    {
        self::$runtimeSamples = [];
    }

    private function inferWorkloadClass(int $callCount, float $p95Ms): string
    {
        if ($callCount >= 100 && $p95Ms < 500) {
            return 'oltp';
        }

        if ($p95Ms >= 1000) {
            return 'dashboard';
        }

        return 'mixed';
    }
}
