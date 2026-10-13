<?php

namespace App\Application\Attendance\Queries;

use App\Application\Attendance\Contracts\AttendanceClockInterface;
use App\Application\Attendance\Contracts\AttendanceReportReadInterface;
use App\Application\Attendance\Contracts\AttendanceWorkspaceReadInterface;
use App\Application\Attendance\Contracts\SchoolScopedRunnerInterface;
use App\Application\Attendance\DTOs\AttendanceViewDTO;
use App\Application\Attendance\Support\AttendancePolicyResolver;
use App\Application\Attendance\Support\RiskReport;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Domain\Attendance\Services\AcademicRiskIndicator;
use App\Domain\Attendance\Services\AttendanceStatistics;

/** One aggregated read per widget group (no request explosion from the page). */
final class GetAttendanceDashboardHandler implements QueryHandler
{
    private const CENTRAL_CAP = 50;

    public function __construct(
        private readonly AttendanceReportReadInterface $reports,
        private readonly AttendanceWorkspaceReadInterface $read,
        private readonly SchoolScopedRunnerInterface $schools,
        private readonly AttendancePolicyResolver $policies,
        private readonly AttendanceClockInterface $clock,
        private readonly RiskReport $risk,
    ) {}

    public function handle(Query $query): AttendanceViewDTO
    {
        assert($query instanceof GetAttendanceDashboardQuery);

        $policy = $this->policies->current($query->schoolId, $query->academicYearId);
        $today = $policy->localDate($this->clock->now());
        $monthAgo = (new \DateTimeImmutable($today))->modify('-29 days')->format('Y-m-d');

        $todayTotals = $this->reports->schoolTotals($query->schoolId, $query->academicYearId, $today, $today);
        $monthTotals = $this->reports->schoolTotals($query->schoolId, $query->academicYearId, $monthAgo, $today);
        $day = $query->date ?? $today;
        $dayTotals = $day === $today ? $todayTotals : $this->reports->schoolTotals($query->schoolId, $query->academicYearId, $day, $day);

        return new AttendanceViewDTO([
            'today' => $today,
            'period' => ['from' => $monthAgo, 'to' => $today],
            'formula' => AttendanceStatistics::FORMULA,
            'today_totals' => $todayTotals + AttendanceStatistics::rates($todayTotals),
            'day' => $day,
            'day_totals' => $dayTotals + AttendanceStatistics::rates($dayTotals),
            'month_totals' => $monthTotals + AttendanceStatistics::rates($monthTotals),
            'top_absentees' => $this->reports->topAbsentees($query->schoolId, $query->academicYearId, $monthAgo, $today, 10),
            'top_risk' => $this->risk->rows($query->schoolId, $query->academicYearId, [], 10, 0)['rows'],
            'risk_method' => AcademicRiskIndicator::METHOD,
            'alerts_open' => $this->read->alerts($query->schoolId, $query->academicYearId, ['status' => 1], 1, 1)['open'],
            'excuses_pending' => $this->read->excuses($query->schoolId, $query->academicYearId, ['status' => 1], 1, 1)['meta']['total'],
            'central' => $this->central($query, $monthAgo, $today),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function central(GetAttendanceDashboardQuery $query, string $from, string $to): array
    {
        $ids = array_slice(array_values(array_unique($query->centralSchoolIds)), 0, self::CENTRAL_CAP);
        if (count($ids) < 2) {
            return [];
        }
        $names = $this->schools->schoolNames($ids);
        $rows = [];
        foreach ($ids as $schoolId) {
            $totals = $this->schools->run($schoolId, fn (): array => $this->reports->schoolTotals($schoolId, $query->academicYearId, $from, $to));
            $rows[] = ['school_id' => $schoolId, 'school_name' => $names[$schoolId] ?? (string) $schoolId] + $totals + AttendanceStatistics::rates($totals);
        }

        return $rows;
    }
}
