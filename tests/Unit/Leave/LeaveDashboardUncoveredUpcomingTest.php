<?php

namespace Tests\Unit\Leave;

use App\Application\Attendance\Contracts\LessonSourceInterface;
use App\Application\Leave\Contracts\LeaveReadRepositoryInterface;
use App\Application\Leave\Queries\GetLeaveDashboardHandler;
use App\Application\Leave\Queries\GetLeaveDashboardQuery;
use App\Application\Leave\Support\LeaveCalendar;
use App\Application\Leave\Support\LeaveViewer;
use App\Domain\Academic\Data\HolidaySnapshot;
use App\Domain\Academic\Repositories\AcademicYearRepositoryInterface;
use App\Domain\Academic\Repositories\HolidayRepositoryInterface;
use App\Domain\Attendance\Data\LessonSlot;
use App\Domain\Timetable\Repositories\TimetableEngineReadRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LeaveDashboardUncoveredUpcomingTest extends TestCase
{
    private const TEACHER = 7;

    /** @return list<array<string, mixed>> */
    private function dashboard(bool $withCoverage = true, bool $seesAll = true): array
    {
        // Today is Sunday 2026-11-01; Tuesday 11-03 is a holiday; Friday 11-06 and Saturday 11-07 are the weekend.
        $read = $this->createMock(LeaveReadRepositoryInterface::class);
        $read->method('dashboardCounts')->willReturn([]);
        $read->method('startingBetween')->willReturn([]);
        $read->method('awayOn')->willReturnCallback(
            static fn (int $school, string $date): array => $date === '2026-11-01'
                ? []
                : [['requester_kind' => 1, 'person_id' => self::TEACHER, 'request_no' => 1]],
        );

        $lessons = $this->createMock(LessonSourceInterface::class);
        $lessons->method('lessonsOn')->willReturnCallback(
            fn (int $school, int $year, string $date): array => [
                $this->lesson($date, 1, self::TEACHER, null),
                $this->lesson($date, 2, self::TEACHER, 99),
                $this->lesson($date, 3, 8, null),
            ],
        );

        $holidays = $this->createMock(HolidayRepositoryInterface::class);
        $holidays->method('listVisible')->willReturn([
            new HolidaySnapshot(1, 1, null, 'عطلة', '2026-11-03', '2026-11-03', 1, '2026-01-01'),
        ]);

        $handler = new GetLeaveDashboardHandler(
            $read,
            $lessons,
            new LeaveCalendar($this->createMock(TimetableEngineReadRepositoryInterface::class), $holidays, $this->createMock(AcademicYearRepositoryInterface::class)),
        );

        return $handler->handle(new GetLeaveDashboardQuery(1, 1, '2026-11-01', new LeaveViewer(5, $seesAll, false, false), $withCoverage));
    }

    private function lesson(string $date, int $id, int $teacher, ?int $substitute): LessonSlot
    {
        return new LessonSlot($id, $date, 1, 1, $teacher, null, $substitute, $id, $id, '08:00', '08:45', null, null, 1, 'رياضيات', 'أ', 'الأول', null, null, 'علي');
    }

    #[Test]
    public function upcoming_lists_uncovered_lessons_of_teachers_on_leave_on_working_days_only(): void
    {
        $dashboard = $this->dashboard();

        $rows = $dashboard['uncovered_upcoming'];
        // Mon 11-02, Wed 11-04, Thu 11-05 and the next Sunday 11-08: the holiday and the weekend are skipped.
        $this->assertSame(['2026-11-02', '2026-11-04', '2026-11-05', '2026-11-08'], array_column($rows, 'date'));
        // Only the teacher's lesson without a substitute: the covered lesson and the other teacher's are not listed.
        $this->assertSame([1], array_values(array_unique(array_column($rows, 'schedule_id'))));
        $this->assertSame([], $dashboard['uncovered_today']);
    }

    #[Test]
    public function coverage_tables_are_skipped_without_the_flag_or_without_school_wide_view(): void
    {
        foreach ([$this->dashboard(withCoverage: false), $this->dashboard(seesAll: false)] as $dashboard) {
            $this->assertSame([], $dashboard['uncovered_upcoming']);
            $this->assertSame([], $dashboard['uncovered_today']);
        }
    }
}
