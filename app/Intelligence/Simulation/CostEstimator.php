<?php

namespace App\Intelligence\Simulation;

class CostEstimator
{
    /**
     * @param  list<array<string, mixed>>  $options
     * @return list<array<string, mixed>>
     */
    public function rankOptions(array $options, string $workload = 'oltp'): array
    {
        $weights = config("intelligence.cost_weights.{$workload}")
            ?? config('intelligence.cost_weights.oltp');

        foreach ($options as &$option) {
            $option['total_score'] = round(
                ($option['performance_score'] ?? 0) * ($weights['performance'] ?? 0.4)
                + (100 - ($option['storage_cost_score'] ?? 50)) * ($weights['storage'] ?? 0.15)
                + (100 - ($option['maintenance_cost_score'] ?? 50)) * ($weights['maintenance'] ?? 0.15)
                + (100 - ($option['complexity_score'] ?? 50)) * ($weights['complexity'] ?? 0.10)
                + (100 - ($option['risk_score'] ?? 50)) * ($weights['risk'] ?? 0.20),
                2
            );
        }
        unset($option);

        usort($options, fn ($a, $b) => ($b['total_score'] ?? 0) <=> ($a['total_score'] ?? 0));

        return $options;
    }

    /**
     * @param  array<string, mixed>  $option
     */
    public function shouldReject(array $option): bool
    {
        if (($option['expected_improvement_pct'] ?? 0) < 20) {
            return true;
        }

        if (($option['write_overhead_pct'] ?? 0) > 25) {
            return true;
        }

        if (($option['risk_score'] ?? 0) > 80) {
            return true;
        }

        return false;
    }
}
