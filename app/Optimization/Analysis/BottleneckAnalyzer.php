<?php

namespace App\Optimization\Analysis;

use App\Intelligence\Models\BaselineSnapshot;
use App\Intelligence\Models\QueryMetric;

final class BottleneckAnalyzer
{
    /**
     * @return list<array<string, mixed>>
     */
    public function analyze(?BaselineSnapshot $baseline = null): array
    {
        $baseline = $baseline ?? BaselineSnapshot::query()->orderByDesc('captured_at')->first();
        $bottlenecks = [];

        $recentMetrics = QueryMetric::query()
            ->where('captured_at', '>=', now()->subHours(24))
            ->orderByDesc('p95_ms')
            ->limit(50)
            ->get();

        foreach ($recentMetrics as $metric) {
            $baselineP95 = $this->baselineP95($baseline, $metric->query_fingerprint);
            $degradationPct = $baselineP95 > 0
                ? (($metric->p95_ms - $baselineP95) / $baselineP95) * 100
                : 0;

            $threshold = (float) config('optimization.anomaly.p95_degradation_pct', 20);

            if ($metric->p95_ms >= 200 || $degradationPct >= $threshold) {
                $bottlenecks[] = [
                    'domain' => 'database',
                    'component' => $metric->query_label ?? $metric->query_fingerprint,
                    'problem' => 'Slow query detected',
                    'evidence' => [
                        'p95_ms' => (float) $metric->p95_ms,
                        'baseline_p95_ms' => $baselineP95,
                        'degradation_pct' => round($degradationPct, 2),
                        'call_count' => (int) $metric->call_count,
                        'query_fingerprint' => $metric->query_fingerprint,
                        'query_label' => $metric->query_label,
                    ],
                    'priority' => $degradationPct >= 50 ? 'P0' : ($degradationPct >= 20 ? 'P1' : 'P2'),
                ];
            }
        }

        return $bottlenecks;
    }

    private function baselineP95(?BaselineSnapshot $baseline, ?string $fingerprint): float
    {
        if ($baseline === null || $fingerprint === null) {
            return 0.0;
        }

        $queryBaselines = $baseline->query_baselines ?? [];

        return (float) ($queryBaselines[$fingerprint]['p95_ms'] ?? 0);
    }
}
