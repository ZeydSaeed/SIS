<?php

namespace App\Application\Attendance\Queries;

use App\Application\Attendance\Contracts\AttendanceClockInterface;
use App\Application\Attendance\Contracts\AttendanceWorkspaceReadInterface;
use App\Application\Attendance\Contracts\LessonSourceInterface;
use App\Application\Attendance\DTOs\AttendanceViewDTO;
use App\Application\Attendance\Support\AttendancePolicyResolver;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Domain\Attendance\Data\LessonSlot;
use App\Domain\Attendance\Repositories\AttendanceSheetRepositoryInterface;
use App\Domain\Attendance\ValueObjects\CalendarDate;

/**
 * The effective timetable of a date for the whole school laid out as a day board: periods (rows) × sections
 * (columns), each lesson with its sheet (status, counts, submission). «Waiting» = lessons that ended with no
 * submitted sheet (none opened, draft or returned). Read only; the timetable is never changed here.
 */
final class GetAttendanceDayBoardHandler implements QueryHandler
{
    /** Sheet statuses that still need the teacher: draft and returned. */
    private const OPEN_STATUSES = [1, 5];

    public function __construct(
        private readonly LessonSourceInterface $lessons,
        private readonly AttendanceWorkspaceReadInterface $read,
        private readonly AttendanceSheetRepositoryInterface $sheets,
        private readonly AttendancePolicyResolver $policies,
        private readonly AttendanceClockInterface $clock,
    ) {}

    public function handle(Query $query): AttendanceViewDTO
    {
        assert($query instanceof GetAttendanceDayBoardQuery);

        $policy = $this->policies->current($query->schoolId, $query->academicYearId);
        $now = $this->clock->now();
        $today = $policy->localDate($now);
        $date = CalendarDate::valid($query->date) ? (string) $query->date : $today;

        $slots = $this->lessons->lessonsOn($query->schoolId, $query->academicYearId, $date);
        $sessions = $this->read->sessionsForLessons($query->schoolId, $date, array_map(static fn (LessonSlot $l): int => $l->scheduleId, $slots));
        $lookups = $this->read->lookups($query->schoolId, $query->academicYearId);

        $cells = [];
        $waiting = [];
        foreach ($slots as $slot) {
            $session = $sessions[$slot->scheduleId] ?? null;
            $ended = $policy->at($slot->date, $slot->endTime) < $now;
            $row = $slot->toArray() + ['session' => $session, 'ended' => $ended];
            $cells[] = $row;
            if ($ended && ($session === null || in_array((int) $session['sheet_status'], self::OPEN_STATUSES, true))) {
                $waiting[] = $row;
            }
        }

        return new AttendanceViewDTO([
            'date' => $date,
            'today' => $today,
            'now' => $now->setTimezone($policy->zone())->format('H:i'),
            'weekday' => (int) (new \DateTimeImmutable($date))->format('w') + 1,
            'timezone' => $policy->timezone,
            'holiday' => $this->sheets->isHoliday($query->schoolId, $query->academicYearId, $date),
            'periods' => $lookups['periods'],
            'classes' => $lookups['classes'],
            'cells' => $cells,
            'waiting' => $waiting,
        ]);
    }
}
