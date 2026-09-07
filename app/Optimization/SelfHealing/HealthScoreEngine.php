<?php

namespace App\Optimization\SelfHealing;

final class HealthScoreEngine
{
    /**
     * @param  array<string, mixed>  $telemetry
     * @param  array<string, mixed>  $adaptiveBaseline
     * @return array<string, mixed>
     */
    public function evaluate(array $telemetry, array $adaptiveBaseline): array
    {
        $dimensions = [];
        $degradedCount = 0;

        foreach (config('optimization.protected_metrics', []) as $metric) {
            $current = (float) ($telemetry[$metric] ?? 0);
            $baselineMetric = $adaptiveBaseline['metrics'][$metric] ?? null;
            $baseline = (float) ($baselineMetric['baseline_value'] ?? 0);

            $score = 100.0;
            $status = 'healthy';
            $degradationPct = 0.0;

            if ($baseline > 0 && $current > 0) {
                if (str_contains($metric, 'hit_ratio')) {
                    $degradationPct = (($baseline - $current) / $baseline) * 100;
                    $threshold = (float) config('optimization.anomaly.cache_hit_decrease_pct', 15);
                } else {
                    $degradationPct = (($current - $baseline) / $baseline) * 100;
                    $threshold = $this->thresholdFor($metric);
                }

                if ($degradationPct >= $threshold) {
                    $status = 'degraded';
                    $score = max(0, 100 - $degradationPct);
                    $degradedCount++;
                }
            }

            $dimensions[$metric] = [
                'current' => $current,
                'baseline' => $baseline,
                'degradation_pct' => round($degradationPct, 2),
                'status' => $status,
                'score' => round($score, 2),
            ];
        }

        $overall = $dimensions === []
            ? 100.0
            : round(collect($dimensions)->avg('score'), 2);

        return [
            'overall_score' => $overall,
            'status' => $degradedCount > 0 ? 'unhealthy' : 'healthy',
            'degraded_dimensions' => $degradedCount,
            'dimensions' => $dimensions,
        ];
    }

    private function thresholdFor(string $metric): float
    {
        return match (true) {
            str_contains($metric, 'p99') => (float) config('optimization.anomaly.p99_degradation_pct', 25),
            str_contains($metric, 'cpu') => (float) config('optimization.anomaly.cpu_degradation_pct', 20),
            str_contains($metric, 'memory') => (float) config('optimization.anomaly.memory_degradation_pct', 20),
            default => (float) config('optimization.anomaly.p95_degradation_pct', 20),
        };
    }
}
