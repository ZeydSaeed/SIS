<?php

namespace App\Application\Attendance\Support;

use App\Application\Attendance\Contracts\AttendanceClockInterface;
use App\Application\Attendance\Contracts\AttendanceReportReadInterface;
use App\Domain\Attendance\Services\AcademicRiskIndicator;

/** Students ranked by the attendance risk indicator (score > 0), with the reasons behind each score. */
final class RiskReport
{
    public function __construct(
        private readonly AttendanceReportReadInterface $reports,
        private readonly AttendancePolicyResolver $policies,
        private readonly AttendanceClockInterface $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function rows(int $schoolId, int $academicYearId, array $filters, int $limit, int $offset): array
    {
        $policy = $this->policies->current($schoolId, $academicYearId);
        $today = $policy->localDate($this->clock->now());
        $scope = array_intersect_key($filters, array_flip(['class_id', 'section_id']));

        $rows = [];
        foreach ($this->reports->riskInputs($schoolId, $academicYearId, $today, $policy->absenceAlertCount, $scope) as $in) {
            $risk = AcademicRiskIndicator::assess($in, $policy);
            if ($risk['score'] > 0) {
                $rows[] = [
                    'student_id' => $in['student_id'], 'full_name' => $in['full_name'], 'student_code' => $in['student_code'],
                    'score' => $risk['score'], 'band' => $risk['band'], 'attendance_rate' => $risk['recent_rate'] === null ? null : round(100 - $risk['recent_rate'], 1),
                    'reasons' => implode(' · ', $risk['reasons']),
                ];
            }
        }
        usort($rows, static fn (array $a, array $b): int => [$b['score'], $a['full_name']] <=> [$a['score'], $b['full_name']]);

        return ['rows' => array_slice($rows, $offset, $limit), 'total' => count($rows)];
    }
}
