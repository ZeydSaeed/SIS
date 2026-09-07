<?php

namespace App\Optimization\Gates;

final class CrossMetricGuard
{
    /**
     * @param  array<string, float|int>  $before
     * @param  array<string, float|int>  $after
     * @return array{passed: bool, regressions: list<string>}
     */
    public function evaluate(array $before, array $after): array
    {
        if (! config('optimization.gates.cross_metric_regression_block', true)) {
            return ['passed' => true, 'regressions' => []];
        }

        $regressions = [];
        $protected = config('optimization.protected_metrics', []);

        foreach ($protected as $metric) {
            $beforeVal = (float) ($before[$metric] ?? 0);
            $afterVal = (float) ($after[$metric] ?? 0);

            if ($beforeVal <= 0) {
                continue;
            }

            // Lower-is-better metrics
            if (str_contains($metric, 'latency') || str_contains($metric, 'error') || str_contains($metric, 'cpu')) {
                $increasePct = (($afterVal - $beforeVal) / $beforeVal) * 100;
                if ($increasePct > 20) {
                    $regressions[] = "{$metric} increased by {$increasePct}%";
                }
            }

            // Higher-is-better metrics
            if (str_contains($metric, 'hit_ratio')) {
                $decreasePct = (($beforeVal - $afterVal) / $beforeVal) * 100;
                if ($decreasePct > 10) {
                    $regressions[] = "{$metric} decreased by {$decreasePct}%";
                }
            }
        }

        return [
            'passed' => $regressions === [],
            'regressions' => $regressions,
        ];
    }
}
