<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Leave\Contracts\LeaveReadRepositoryInterface;
use App\Application\Leave\Support\LeaveCalendar;

/**
 * «التقارير»: aggregated in SQL for the year. Without «عرض الإجازات الحساسة», rows of sensitive types keep their
 * numbers but lose the type name — totals stay right, the diagnosis-revealing label does not leave the server.
 */
final class GetLeaveReportHandler implements QueryHandler
{
    public function __construct(
        private readonly LeaveReadRepositoryInterface $read,
        private readonly LeaveCalendar $calendar,
    ) {}

    /** @return list<array<string, mixed>> */
    public function handle(Query $query): array
    {
        assert($query instanceof GetLeaveReportQuery);
        if (! in_array($query->kind, GetLeaveReportQuery::KINDS, true)) {
            return [];
        }
        if ($query->kind === 'holidays') {
            $year = $this->calendar->year($query->academicYearId);

            return $this->calendar->holidaysBetween($query->schoolId, $query->academicYearId, substr($year->startDate, 0, 10), substr($year->endDate, 0, 10));
        }

        return array_map(static function (array $row) use ($query): array {
            $hide = ($row['is_sensitive'] ?? false) && ! $query->viewer->seesSensitive;
            if ($hide) {
                $row['type_name'] = null;
            }

            return $row + ['masked' => $hide];
        }, $this->read->report($query->schoolId, $query->academicYearId, $query->kind, $query->filters));
    }
}
