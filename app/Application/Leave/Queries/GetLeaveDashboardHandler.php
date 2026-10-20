<?php

namespace App\Application\Leave\Queries;

use App\Application\Attendance\Contracts\LessonSourceInterface;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Leave\Contracts\LeaveReadRepositoryInterface;
use App\Application\Leave\Support\LeaveCalendar;
use App\Application\Leave\Support\LeavePrivacy;
use App\Domain\Attendance\Data\LessonSlot;
use App\Domain\Leave\ValueObjects\RequesterKind;

/**
 * «لوحة متابعة الإجازات»: live counts (every card opens the matching list), who is away today, what starts in the
 * next two weeks, and the lessons (today and the next seven days) of teachers on approved leave that still have no
 * substitute.
 */
final class GetLeaveDashboardHandler implements QueryHandler
{
    private const AHEAD_DAYS = 14;

    private const COVER_AHEAD_DAYS = 7;

    public function __construct(
        private readonly LeaveReadRepositoryInterface $read,
        private readonly LessonSourceInterface $lessons,
        private readonly LeaveCalendar $calendar,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Query $query): array
    {
        assert($query instanceof GetLeaveDashboardQuery);
        $own = $query->viewer->ownScope();
        $mask = static fn (array $row): array => LeavePrivacy::mask($row, $query->viewer);
        $away = $this->read->awayOn($query->schoolId, $query->today, $own);
        $today = new \DateTimeImmutable($query->today);
        $cover = $query->withCoverage && $query->viewer->seesAll;

        return [
            'counts' => $this->read->dashboardCounts($query->schoolId, $query->academicYearId, $query->today, $own),
            'away_today' => array_map($mask, $away),
            'upcoming' => array_map($mask, $this->read->startingBetween(
                $query->schoolId,
                $today->modify('+1 day')->format('Y-m-d'),
                $today->modify('+'.self::AHEAD_DAYS.' days')->format('Y-m-d'),
                $own,
            )),
            'uncovered_today' => $cover ? $this->uncovered($query, $query->today, $away) : [],
            'uncovered_upcoming' => $cover ? $this->uncoveredUpcoming($query, $today) : [],
        ];
    }

    /**
     * Lessons of the next days (working days only, holidays skipped) of teachers whose approved leave covers the
     * day and that still have no substitute — one approved-leave read and one timetable read per working day.
     *
     * @return list<array<string, int|string|null>>
     */
    private function uncoveredUpcoming(GetLeaveDashboardQuery $query, \DateTimeImmutable $today): array
    {
        $weekdays = $this->calendar->workingWeekdays($query->schoolId, $query->academicYearId);
        $holidays = $this->calendar->holidayDates($query->schoolId, $query->academicYearId);
        $out = [];
        for ($i = 1; $i <= self::COVER_AHEAD_DAYS; $i++) {
            $day = $today->modify("+{$i} day");
            $date = $day->format('Y-m-d');
            if (! in_array((int) $day->format('w') + 1, $weekdays, true) || isset($holidays[$date])) {
                continue;
            }
            array_push($out, ...$this->uncovered($query, $date, $this->read->awayOn($query->schoolId, $date, null)));
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $away
     * @return list<array<string, int|string|null>>
     */
    private function uncovered(GetLeaveDashboardQuery $query, string $date, array $away): array
    {
        $teachers = [];
        foreach ($away as $row) {
            if ((int) $row['requester_kind'] === RequesterKind::Teacher->value) {
                $teachers[(int) $row['person_id']] = true;
            }
        }
        if ($teachers === []) {
            return [];
        }

        return array_values(array_map(
            static fn (LessonSlot $l): array => $l->toArray(),
            array_filter(
                $this->lessons->lessonsOn($query->schoolId, $query->academicYearId, $date),
                static fn (LessonSlot $l): bool => $l->substituteTeacherId === null && isset($teachers[$l->teacherId]),
            ),
        ));
    }
}
