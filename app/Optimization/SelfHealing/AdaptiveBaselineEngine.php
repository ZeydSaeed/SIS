<?php

namespace App\Optimization\SelfHealing;

use Illuminate\Support\Facades\File;

final class AdaptiveBaselineEngine
{
    public function __construct(
        private readonly BaselineContextMatcher $contextMatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $telemetry
     * @return array<string, mixed>
     */
    public function loadCompatible(array $telemetry): array
    {
        $baseline = $this->load();
        $fingerprint = $this->contextMatcher->fingerprintFromTelemetry($telemetry);

        if (($baseline['metrics'] ?? []) !== []
            && ! $this->contextMatcher->isCompatible($baseline, $fingerprint)) {
            $this->markStale('context_mismatch');

            return $this->emptyBaseline();
        }

        return $baseline;
    }

    /**
     * @param  array<string, mixed>  $telemetry
     * @return array<string, mixed>
     */
    public function load(): array
    {
        $path = config('optimization.adaptive_baseline_path');
        if (! File::exists($path)) {
            return $this->emptyBaseline();
        }

        $data = json_decode(File::get($path), true);

        return is_array($data) ? $data : $this->emptyBaseline();
    }

    /**
     * @param  array<string, mixed>  $telemetry
     * @param  list<array<string, mixed>>  $anomalies
     */
    public function update(array $telemetry, array $anomalies = []): array
    {
        $baseline = $this->load();
        $hasActiveAnomaly = $anomalies !== [];

        foreach ($this->metricKeys() as $key) {
            $current = (float) ($telemetry[$key] ?? 0);
            if ($current <= 0) {
                continue;
            }

            if (! isset($baseline['metrics'][$key])) {
                $baseline['metrics'][$key] = $this->initMetric($current);

                continue;
            }

            $metric = $baseline['metrics'][$key];
            $degraded = $this->isDegraded($current, $metric);

            if ($degraded) {
                $metric['degraded_observations'] = ($metric['degraded_observations'] ?? 0) + 1;
                $metric['healthy_streak'] = 0;
                $baseline['metrics'][$key] = $metric;

                continue;
            }

            $metric['healthy_streak'] = ($metric['healthy_streak'] ?? 0) + 1;
            $metric['degraded_observations'] = 0;
            $metric['observations'][] = $current;
            $metric['observations'] = array_slice($metric['observations'], -100);
            $metric['percentiles'] = $this->computePercentiles($metric['observations']);

            if (! $hasActiveAnomaly && $this->canAdapt($metric)) {
                $newBaseline = $metric['percentiles']['p95'] ?? $current;
                $oldBaseline = (float) ($metric['baseline_value'] ?? $current);
                $maxShiftPct = (float) config('optimization.baseline.adaptation_max_shift_pct', 15);
                $shiftPct = $oldBaseline > 0 ? abs(($newBaseline - $oldBaseline) / $oldBaseline) * 100 : 0;

                if ($shiftPct <= $maxShiftPct) {
                    $metric['baseline_value'] = $newBaseline;
                    $metric['last_adapted_at'] = now()->toIso8601String();
                }
            }

            $baseline['metrics'][$key] = $metric;
        }

        $baseline['updated_at'] = now()->toIso8601String();
        $baseline['context_fingerprint'] = $telemetry['context_fingerprint'] ?? [];

        if (($baseline['stale'] ?? false) && $this->warmupComplete($baseline)) {
            $baseline['stale'] = false;
            $baseline['stale_reason'] = null;
        }

        File::ensureDirectoryExists(dirname(config('optimization.adaptive_baseline_path')));
        File::put(config('optimization.adaptive_baseline_path'), json_encode($baseline, JSON_PRETTY_PRINT));

        return $baseline;
    }

    public function isStale(): bool
    {
        $baseline = $this->load();

        return ($baseline['stale'] ?? false) === true;
    }

    public function markStale(string $reason): void
    {
        $baseline = $this->load();
        $baseline['stale'] = true;
        $baseline['stale_reason'] = $reason;
        $baseline['stale_at'] = now()->toIso8601String();

        File::ensureDirectoryExists(dirname(config('optimization.adaptive_baseline_path')));
        File::put(config('optimization.adaptive_baseline_path'), json_encode($baseline, JSON_PRETTY_PRINT));
    }

    public function clearStale(): void
    {
        $baseline = $this->load();
        $baseline['stale'] = false;
        $baseline['stale_reason'] = null;
        File::put(config('optimization.adaptive_baseline_path'), json_encode($baseline, JSON_PRETTY_PRINT));
    }

    /**
     * @param  array<string, mixed>  $metric
     */
    public function isDegraded(float $current, array $metric): bool
    {
        $baseline = (float) ($metric['baseline_value'] ?? 0);
        if ($baseline <= 0) {
            return false;
        }

        $degradationPct = (($current - $baseline) / $baseline) * 100;
        $threshold = (float) config('optimization.anomaly.p95_degradation_pct', 20);

        return $degradationPct >= $threshold;
    }

    /**
     * @param  array<string, mixed>  $baseline
     */
    private function warmupComplete(array $baseline): bool
    {
        $required = (int) config('optimization.baseline.min_healthy_observations_to_adapt', 10);

        foreach ($baseline['metrics'] ?? [] as $metric) {
            if (($metric['healthy_streak'] ?? 0) < $required) {
                return false;
            }
        }

        return ($baseline['metrics'] ?? []) !== [];
    }

    /**
     * @return list<string>
     */
    private function metricKeys(): array
    {
        return [
            'p95_latency_ms',
            'p99_latency_ms',
            'cpu_pct',
            'memory_mb',
            'db_queries_per_request',
            'cache_hit_ratio',
            'error_rate_pct',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function initMetric(float $value): array
    {
        return [
            'baseline_value' => $value,
            'observations' => [$value],
            'percentiles' => $this->computePercentiles([$value]),
            'healthy_streak' => 1,
            'degraded_observations' => 0,
            'last_adapted_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $metric
     */
    private function canAdapt(array $metric): bool
    {
        if (! config('optimization.baseline.prevent_poisoning', true)) {
            return true;
        }

        $required = (int) config('optimization.baseline.min_healthy_observations_to_adapt', 10);

        return ($metric['healthy_streak'] ?? 0) >= $required;
    }

    /**
     * @param  list<float>  $values
     * @return array<string, float>
     */
    private function computePercentiles(array $values): array
    {
        if ($values === []) {
            return [];
        }

        sort($values);
        $count = count($values);

        return [
            'p50' => $this->percentile($values, 50, $count),
            'p75' => $this->percentile($values, 75, $count),
            'p90' => $this->percentile($values, 90, $count),
            'p95' => $this->percentile($values, 95, $count),
            'p99' => $this->percentile($values, 99, $count),
            'mean' => array_sum($values) / $count,
            'min' => $values[0],
            'max' => $values[$count - 1],
        ];
    }

    /**
     * @param  list<float>  $values
     */
    private function percentile(array $values, int $percentile, int $count): float
    {
        $index = (int) ceil(($percentile / 100) * $count) - 1;

        return $values[max(0, min($count - 1, $index))];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyBaseline(): array
    {
        return [
            'metrics' => [],
            'updated_at' => null,
            'context_fingerprint' => [],
        ];
    }
}
