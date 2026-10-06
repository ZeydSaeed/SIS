<?php

namespace Tests\Unit\Domain;

use App\Domain\Timetable\Data\PeriodSnapshot;
use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Services\ScheduleShiftPlanner;
use App\Domain\Timetable\Services\TimetableAuditor;
use App\Domain\Timetable\Services\TimetableAutoPlacer;
use App\Domain\Timetable\Support\SchoolWeek;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Audit, auto-placement and «زحف» on a school day of 1 2 [break] 3 4 5 (period ids 11 12 [13] 14 15 16). */
final class TimetableBuilderServicesTest extends TestCase
{
    private const MATH = 100;

    private const LAB = 200; // practical

    private const ARABIC = 300;

    #[Test]
    public function auto_place_fills_the_section_and_keeps_practicals_as_adjacent_doubles(): void
    {
        $board = $this->board([], [
            ['section_id' => 1, 'subject_id' => self::LAB, 'teacher_id' => 7, 'weekly' => 4],
            ['section_id' => 1, 'subject_id' => self::MATH, 'teacher_id' => 8, 'weekly' => 5],
        ]);

        $plan = (new TimetableAutoPlacer)->plan($board, 1);

        $this->assertSame(0, $plan['unplaced']);
        $this->assertCount(9, $plan['placements']);

        $labByDay = [];
        foreach ($plan['placements'] as $p) {
            if ($p['subject_id'] === self::LAB) {
                $labByDay[$p['day']][] = $p['period_id'];
            }
        }
        foreach ($labByDay as $periods) {
            $this->assertCount(2, $periods);
            $this->assertTrue($board->adjacent($periods[0], $periods[1]), 'practical lessons must be back to back');
        }

        $merged = new TimetableBoard($board->periods, $this->asSchedules($plan['placements'], 1), $board->requirements, $board->teacherSubjects, [7, 8], [self::LAB]);
        $this->assertSame([], (new TimetableAuditor)->audit($merged));
    }

    #[Test]
    public function auto_place_never_double_books_a_teacher_and_respects_the_daily_limits(): void
    {
        // Teacher 8 already teaches section 2 every Sunday period.
        $busy = [];
        foreach ([11, 12, 14, 15, 16] as $i => $period) {
            $busy[] = ['id' => 900 + $i, 'section_id' => 2, 'day_of_week' => 1, 'period_id' => $period, 'subject_id' => self::MATH, 'teacher_id' => 8];
        }
        $board = $this->board($busy, [['section_id' => 1, 'subject_id' => self::MATH, 'teacher_id' => 8, 'weekly' => 12]]);

        $plan = (new TimetableAutoPlacer)->plan($board, 1);

        foreach ($plan['placements'] as $p) {
            $this->assertNotSame(1, $p['day'], 'teacher 8 is busy all Sunday');
        }
        // Max 2 of the subject a day over the 4 free days → 8 placed, 4 left.
        $this->assertCount(4 * SchoolWeek::MAX_SUBJECT_LESSONS_PER_DAY, $plan['placements']);
        $this->assertSame(4, $plan['unplaced']);
    }

    #[Test]
    public function audit_reports_every_kind_of_problem_worst_first(): void
    {
        $schedules = [
            // section 1, Sunday: math 3 times (repeat), a hole at period 12, lab single (split).
            ['id' => 1, 'section_id' => 1, 'day_of_week' => 1, 'period_id' => 11, 'subject_id' => self::MATH, 'teacher_id' => 8],
            ['id' => 2, 'section_id' => 1, 'day_of_week' => 1, 'period_id' => 14, 'subject_id' => self::MATH, 'teacher_id' => 8],
            ['id' => 3, 'section_id' => 1, 'day_of_week' => 1, 'period_id' => 15, 'subject_id' => self::MATH, 'teacher_id' => 8],
            ['id' => 4, 'section_id' => 1, 'day_of_week' => 1, 'period_id' => 16, 'subject_id' => self::LAB, 'teacher_id' => 7],
            // teacher 8 double-booked in section 2; arabic taught by a teacher without the subject, in a break.
            ['id' => 5, 'section_id' => 2, 'day_of_week' => 1, 'period_id' => 11, 'subject_id' => self::MATH, 'teacher_id' => 8],
            ['id' => 6, 'section_id' => 2, 'day_of_week' => 2, 'period_id' => 13, 'subject_id' => self::ARABIC, 'teacher_id' => 7],
            // teacher 99 left the school.
            ['id' => 7, 'section_id' => 2, 'day_of_week' => 3, 'period_id' => 11, 'subject_id' => self::MATH, 'teacher_id' => 99],
            // section 1, Monday: the lab double is split by a free period (11 · 14).
            ['id' => 8, 'section_id' => 1, 'day_of_week' => 2, 'period_id' => 11, 'subject_id' => self::LAB, 'teacher_id' => 7],
            ['id' => 9, 'section_id' => 1, 'day_of_week' => 2, 'period_id' => 14, 'subject_id' => self::LAB, 'teacher_id' => 7],
        ];
        $board = $this->board($schedules, [
            ['section_id' => 1, 'subject_id' => self::MATH, 'teacher_id' => 8, 'weekly' => 2],
            ['section_id' => 1, 'subject_id' => self::LAB, 'teacher_id' => 7, 'weekly' => 4],
            ['section_id' => 1, 'subject_id' => self::ARABIC, 'teacher_id' => 7, 'weekly' => 1],
        ]);

        $issues = (new TimetableAuditor)->audit($board);
        $codes = array_column($issues, 'code');

        foreach (['teacher_double_booked', 'teacher_not_assigned', 'teacher_inactive', 'lesson_in_break'] as $error) {
            $this->assertContains($error, $codes);
        }
        foreach (['lesson_over_placed', 'subject_day_repeat', 'section_day_gap'] as $warning) {
            $this->assertContains($warning, $codes);
        }
        foreach (['lesson_under_placed', 'practical_split'] as $info) {
            $this->assertContains($info, $codes);
        }

        $severities = array_column($issues, 'severity');
        $this->assertSame('error', $severities[0]);
        $this->assertSame('info', $severities[count($severities) - 1]);

        $over = $issues[array_search('lesson_over_placed', $codes, true)];
        $this->assertSame(1, $over['count']);
        $this->assertSame([1, 2, 3], $over['schedule_ids']);
    }

    #[Test]
    public function audit_flags_a_teacher_past_six_lessons_a_day(): void
    {
        $schedules = [];
        foreach ([11, 12, 14, 15, 16] as $i => $period) {
            $schedules[] = ['id' => $i + 1, 'section_id' => 1, 'day_of_week' => 4, 'period_id' => $period, 'subject_id' => self::MATH, 'teacher_id' => 8];
        }
        $schedules[] = ['id' => 6, 'section_id' => 2, 'day_of_week' => 4, 'period_id' => 11 + 100, 'subject_id' => self::MATH, 'teacher_id' => 8];
        $schedules[] = ['id' => 7, 'section_id' => 3, 'day_of_week' => 4, 'period_id' => 12 + 100, 'subject_id' => self::MATH, 'teacher_id' => 8];

        $codes = array_column((new TimetableAuditor)->audit($this->board($schedules, [])), 'code');

        $this->assertContains('teacher_day_overload', $codes);
    }

    #[Test]
    public function shift_slides_the_block_up_to_the_first_free_period(): void
    {
        $planner = new ScheduleShiftPlanner;
        $periods = [11, 12, 14, 15, 16];

        // Lessons at 11, 12, 14 · free 15 → shifting 11 later moves the block 11→12, 12→14, 14→15.
        $plan = $planner->plan($periods, [11 => 1, 12 => 2, 14 => 3], 1, 1);
        $this->assertNull($plan['error']);
        $this->assertSame([
            ['schedule_id' => 1, 'period_id' => 12],
            ['schedule_id' => 2, 'period_id' => 14],
            ['schedule_id' => 3, 'period_id' => 15],
        ], $plan['moves']);

        // Earlier: lesson at 15 with 14 free → only it moves.
        $this->assertSame([['schedule_id' => 9, 'period_id' => 14]], $planner->plan($periods, [11 => 1, 15 => 9], 9, -1)['moves']);

        // A full day has no room.
        $this->assertSame('timetable.shift_no_room', $planner->plan($periods, [11 => 1, 12 => 2, 14 => 3, 15 => 4, 16 => 5], 1, 1)['error']);
        $this->assertSame('timetable.shift_no_room', $planner->plan($periods, [11 => 1], 1, -1)['error']);
    }

    /**
     * @param  list<array{id: int, section_id: int, day_of_week: int, period_id: int, subject_id: int, teacher_id: int}>  $schedules
     * @param  list<array{section_id: int, subject_id: int, teacher_id: int, weekly: int|null}>  $requirements
     */
    private function board(array $schedules, array $requirements): TimetableBoard
    {
        $periods = [
            new PeriodSnapshot(11, 1, 1, '08:00:00', '08:45:00', 1),
            new PeriodSnapshot(12, 1, 2, '08:45:00', '09:30:00', 1),
            new PeriodSnapshot(13, 1, 3, '09:30:00', '09:45:00', 2),
            new PeriodSnapshot(14, 1, 4, '09:45:00', '10:30:00', 1),
            new PeriodSnapshot(15, 1, 5, '10:30:00', '11:15:00', 1),
            new PeriodSnapshot(16, 1, 6, '11:15:00', '12:00:00', 1),
        ];

        return new TimetableBoard(
            periods: $periods,
            schedules: $schedules,
            requirements: $requirements,
            teacherSubjects: ['8:'.self::MATH => true, '7:'.self::LAB => true, '99:'.self::MATH => true],
            activeTeacherIds: [7, 8],
            practicalSubjectIds: [self::LAB],
        );
    }

    /** @return list<array{id: int, section_id: int, day_of_week: int, period_id: int, subject_id: int, teacher_id: int}> */
    private function asSchedules(array $placements, int $sectionId): array
    {
        return array_map(static fn (array $p, int $i): array => [
            'id' => $i + 1, 'section_id' => $sectionId, 'day_of_week' => $p['day'], 'period_id' => $p['period_id'], 'subject_id' => $p['subject_id'], 'teacher_id' => $p['teacher_id'],
        ], $placements, array_keys($placements));
    }
}
