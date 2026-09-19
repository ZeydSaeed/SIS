<?php

namespace App\Domain\Admission\Services;

/**
 * Occupancy % per pipeline stage and weighted overall admission progress.
 */
final class AdmissionWorkflowProgressCalculator
{
    /**
     * @param  list<int>  $pipelineStatuses  Ordered pipeline status values
     * @param  list<int>  $applicationStatuses  Current application status values
     * @return array{overall_percent: int, stage_percents: array<int, int>}
     */
    public function calculate(array $pipelineStatuses, array $applicationStatuses): array
    {
        $counts = [];
        foreach ($applicationStatuses as $status) {
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        return $this->calculateFromCounts($pipelineStatuses, $counts);
    }

    /**
     * Aggregate-friendly path for large years — never expands millions of rows.
     *
     * @param  list<int>  $pipelineStatuses
     * @param  array<int, int>  $countsByStatus  status => count
     * @return array{overall_percent: int, stage_percents: array<int, int>}
     */
    public function calculateFromCounts(array $pipelineStatuses, array $countsByStatus): array
    {
        $indexByStatus = [];
        $stagePercents = [];
        foreach ($pipelineStatuses as $index => $status) {
            $indexByStatus[$status] = $index;
            $stagePercents[$status] = 0;
        }

        $total = 0;
        foreach ($countsByStatus as $count) {
            $total += max(0, (int) $count);
        }

        $length = count($pipelineStatuses);
        if ($total === 0 || $length === 0) {
            return [
                'overall_percent' => 0,
                'stage_percents' => $stagePercents,
            ];
        }

        $progressSum = 0;
        foreach ($countsByStatus as $status => $count) {
            $count = (int) $count;
            if ($count <= 0) {
                continue;
            }
            if (isset($indexByStatus[$status])) {
                $progressSum += ($indexByStatus[$status] + 1) * $count;
            }
        }

        foreach ($pipelineStatuses as $status) {
            $stagePercents[$status] = (int) round((($countsByStatus[$status] ?? 0) / $total) * 100);
        }

        return [
            'overall_percent' => (int) round(($progressSum / ($total * $length)) * 100),
            'stage_percents' => $stagePercents,
        ];
    }
}
