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
        $indexByStatus = [];
        $stagePercents = [];
        foreach ($pipelineStatuses as $index => $status) {
            $indexByStatus[$status] = $index;
            $stagePercents[$status] = 0;
        }

        $total = count($applicationStatuses);
        $length = count($pipelineStatuses);
        if ($total === 0 || $length === 0) {
            return [
                'overall_percent' => 0,
                'stage_percents' => $stagePercents,
            ];
        }

        $counts = [];
        $progressSum = 0;
        foreach ($applicationStatuses as $status) {
            $counts[$status] = ($counts[$status] ?? 0) + 1;
            if (isset($indexByStatus[$status])) {
                $progressSum += $indexByStatus[$status] + 1;
            }
        }

        foreach ($pipelineStatuses as $status) {
            $stagePercents[$status] = (int) round((($counts[$status] ?? 0) / $total) * 100);
        }

        return [
            'overall_percent' => (int) round(($progressSum / ($total * $length)) * 100),
            'stage_percents' => $stagePercents,
        ];
    }
}
