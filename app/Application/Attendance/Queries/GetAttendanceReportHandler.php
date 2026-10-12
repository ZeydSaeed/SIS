<?php

namespace App\Application\Attendance\Queries;

use App\Application\Attendance\Contracts\AttendanceReportReadInterface;
use App\Application\Attendance\DTOs\AttendanceViewDTO;
use App\Application\Attendance\Support\RiskReport;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\Services\AcademicRiskIndicator;
use App\Domain\Attendance\Services\AttendanceStatistics;

/**
 * Report rows + rates (one formula, stated on the report), with the filters and generation time echoed so a
 * printed / exported copy says exactly what it contains.
 */
final class GetAttendanceReportHandler implements QueryHandler
{
    private const RATE_TYPES = ['students', 'sections', 'subjects', 'teachers', 'daily', 'lesson_types', 'periods'];

    public function __construct(
        private readonly AttendanceReportReadInterface $reports,
        private readonly RiskReport $risk,
    ) {}

    public function handle(Query $query): AttendanceViewDTO
    {
        assert($query instanceof GetAttendanceReportQuery);

        if (! in_array($query->type, AttendanceReportReadInterface::TYPES, true)) {
            throw AttendanceRuleViolation::reportTypeInvalid($query->type);
        }
        $limit = $query->export ? GetAttendanceReportQuery::EXPORT_CAP + 1 : $query->perPage;
        $offset = $query->export ? 0 : ($query->page - 1) * $query->perPage;
        $result = $query->type === 'risk'
            ? $this->risk->rows($query->schoolId, $query->academicYearId, $query->filters, $limit, $offset)
            : $this->reports->report($query->schoolId, $query->academicYearId, $query->type, $query->filters, $limit, $offset);

        $rows = $result['rows'];
        $truncated = $query->export && count($rows) > GetAttendanceReportQuery::EXPORT_CAP;
        if ($truncated) {
            $rows = array_slice($rows, 0, GetAttendanceReportQuery::EXPORT_CAP);
        }
        if (in_array($query->type, self::RATE_TYPES, true)) {
            $rows = array_map(static fn (array $r): array => $r + AttendanceStatistics::rates($r), $rows);
        }

        return new AttendanceViewDTO([
            'type' => $query->type,
            'filters' => $query->filters,
            'rows' => $rows,
            'meta' => ['page' => $query->page, 'per_page' => $query->perPage, 'total' => $result['total'], 'last_page' => max(1, (int) ceil($result['total'] / max(1, $query->perPage)))],
            'truncated' => $truncated,
            'formula' => $query->type === 'risk' ? AcademicRiskIndicator::METHOD : AttendanceStatistics::FORMULA,
            'generated_at' => (new \DateTimeImmutable)->format(DATE_ATOM),
        ]);
    }
}
