<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Leave\Contracts\LeaveReadRepositoryInterface;
use App\Application\Leave\Support\LeaveCalendar;
use App\Application\Leave\Support\LeavePrivacy;
use App\Domain\Leave\Services\LeaveDurationCalculator;

/**
 * «تقويم الإجازات والعطل»: holidays of the school calendar, staff leave (pending kept apart from approved), and —
 * only for users who may review excuses — students' absence excuses, for a bounded window. Filters and masking
 * are applied here, on the server.
 */
final class GetLeaveCalendarHandler implements QueryHandler
{
    public function __construct(
        private readonly LeaveReadRepositoryInterface $read,
        private readonly LeaveCalendar $calendar,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Query $query): array
    {
        assert($query instanceof GetLeaveCalendarQuery);
        [$from, $to] = $this->window($query->from, $query->to);
        $layers = $query->filters['layers'] ?? ['holidays', 'staff', 'students'];

        return [
            'from' => $from,
            'to' => $to,
            'working_days' => $this->calendar->workingWeekdays($query->schoolId, $query->academicYearId),
            'holidays' => in_array('holidays', $layers, true) ? $this->calendar->holidaysBetween($query->schoolId, $query->academicYearId, $from, $to) : [],
            'leave' => in_array('staff', $layers, true)
                ? array_map(static fn (array $row): array => LeavePrivacy::mask($row, $query->viewer), $this->read->calendar($query->schoolId, $from, $to, $query->filters, $query->viewer->ownScope()))
                : [],
            'students' => in_array('students', $layers, true) && $query->viewer->seesStudentExcuses ? $this->read->studentExcuses($query->schoolId, $from, $to) : [],
        ];
    }

    /** @return array{0: string, 1: string} a valid window of at most MAX_DAYS days */
    private function window(string $from, string $to): array
    {
        $today = (new \DateTimeImmutable)->format('Y-m-d');
        $from = LeaveDurationCalculator::isDate($from) ? $from : $today;
        $to = LeaveDurationCalculator::isDate($to) && $to >= $from ? $to : $from;
        $limit = (new \DateTimeImmutable($from))->modify('+'.(GetLeaveCalendarQuery::MAX_DAYS - 1).' days')->format('Y-m-d');

        return [$from, min($to, $limit)];
    }
}
