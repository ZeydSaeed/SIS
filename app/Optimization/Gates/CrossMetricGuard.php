<?php

namespace App\Optimization\Gates;

final class CrossMetricGuard
{
    /**
     * @param  array<string, float|null>  $before
     * @param  array<string, float|null>  $after
     * @return array{passed: bool, regressions: list<string>, unknown_critical: list<string>}
     */
    public function evaluate(array $before, array $after): array
    {
        if (! config('optimization.gates.cross_metric_regression_block', true)) {
            return ['passed' => true, 'regressions' => [], 'unknown_critical' => []];
        }

        $regressions = [];
        $unknownCritical = [];
        $protected = config('optimization.protected_metrics', []);
        $latencyThreshold = (float) config('optimization.gates.cross_metric_latency_regression_pct', 20);
        $cacheThreshold = (float) config('optimization.gates.cross_metric_cache_decrease_pct', 10);
        $memoryThreshold = (float) config('optimization.gates.cross_metric_memory_regression_pct', 20);
        $errorThreshold = (float) config('optimization.gates.cross_metric_error_regression_pct', 5);

        foreach ($protected as $metric) {
            $beforeVal = $before[$metric] ?? null;
            $afterVal = $after[$metric] ?? null;

            if ($beforeVal === null || $afterVal === null) {
                if (in_array($metric, ['p95_latency_ms', 'error_rate_pct', 'cache_hit_ratio'], true)) {
                    $unknownCritical[] = $metric;
                }

                continue;
            }

            $beforeVal = (float) $beforeVal;
            $afterVal = (float) $afterVal;

            if ($beforeVal <= 0) {
                continue;
            }

            if (str_contains($metric, 'latency') || str_contains($metric, 'cpu')) {
                $increasePct = (($afterVal - $beforeVal) / $beforeVal) * 100;
                if ($increasePct > $latencyThreshold) {
                    $regressions[] = "{$metric} increased by ".round($increasePct, 2).'%';
                }
            }

            if (str_contains($metric, 'memory')) {
                $increasePct = (($afterVal - $beforeVal) / $beforeVal) * 100;
                if ($increasePct > $memoryThreshold) {
                    $regressions[] = "{$metric} increased by ".round($increasePct, 2).'%';
                }
            }

            if (str_contains($metric, 'error')) {
                $increase = $afterVal - $beforeVal;
                if ($increase > $errorThreshold) {
                    $regressions[] = "{$metric} increased by {$increase}";
                }
            }

            if (str_contains($metric, 'hit_ratio')) {
                $decreasePct = (($beforeVal - $afterVal) / $beforeVal) * 100;
                if ($decreasePct > $cacheThreshold) {
                    $regressions[] = "{$metric} decreased by ".round($decreasePct, 2).'%';
                }
            }
        }

        $blockOnUnknown = config('optimization.gates.block_autonomous_on_unknown_critical', true);
        $passed = $regressions === [] && (! $blockOnUnknown || $unknownCritical === []);

        return [
            'passed' => $passed,
            'regressions' => $regressions,
            'unknown_critical' => $unknownCritical,
        ];
    }
}
