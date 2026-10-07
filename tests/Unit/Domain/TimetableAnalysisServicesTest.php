<?php

namespace Tests\Unit\Domain;

use App\Domain\Timetable\Data\PeriodSnapshot;
use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Services\TeacherWorkloadAnalyzer;
use App\Domain\Timetable\Services\TimetableAdvisor;
use App\Domain\Timetable\Services\TimetableAuditor;
use App\Domain\Timetable\Services\TimetableAutoPlacer;
use App\Domain\Timetable\Services\TimetableQualityScorer;
use App\Domain\Timetable\Support\DayRuns;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Readiness, workload and quality on a school day of 1 2 [break 15'] 3 4 5 (period ids 11 12 [13] 14 15 16):
 * 5 lessons × 5 days = 25 slots; doubles 11-12, 14-15, 15-16 (the main break splits 12-14).
 */
final class TimetableAnalysisServicesTest extends TestCase
{
    private const MATH = 100;

    private const LAB = 200; // practical

    private const ARABIC = 300;

    #[Test]
    public function advisor_is_ready_when_the_lessons_fit_the_week(): void
    {
        $board = $this->board([], [
            ['section_id' => 1, 'subject_id' => self::LAB, 'teacher_id' => 7, 'weekly' => 4],
            ['section_id' => 1, 'subject_id' => self::MATH, 'teacher_id' => 8, 'weekly' => 5],
        ], sectionIds: [1, 2]);

        $advice = (new TimetableAdvisor)->advise($board);

        $this->assertSame(TimetableAdvisor::READY, $advice['verdict']);
        $this->assertSame([['severity' => 'info', 'code' => 'section_without_lessons', 'section_id' => 2, 'teacher_id' => null, 'subject_id' => null, 'count' => null, 'limit' => null, 'detail' => null]], $advice['findings']);
        $this->assertSame(['overall' => 90, 'school_day' => 100, 'weekly_loads' => 100, 'qualified' => 100, 'sections' => 50, 'capacity' => 100], $advice['readiness']);
        $this->assertSame(['sections' => 2, 'teachers' => 2, 'requirements' => 2, 'weekly_lessons' => 9, 'lesson_periods' => 5, 'slots_per_week' => 25], $advice['totals']);
    }

    #[Test]
    public function advisor_blocks_what_no_timetable_can_hold_and_explains_why(): void
    {
        $board = $this->board([], [
            // Teacher 8: 12 + 10 + 10 = 32 math lessons > 25 slots; 12 a week > 2 a day × 5 days.
            ['section_id' => 1, 'subject_id' => self::MATH, 'teacher_id' => 8, 'weekly' => 12],
            ['section_id' => 2, 'subject_id' => self::MATH, 'teacher_id' => 8, 'weekly' => 10],
            ['section_id' => 3, 'subject_id' => self::MATH, 'teacher_id' => 8, 'weekly' => 10],
            // Section 4: 26 lab lessons > 25 slots of the week.
            ['section_id' => 4, 'subject_id' => self::LAB, 'teacher_id' => 7, 'weekly' => 26],
            // Arabic: teacher 8 is not assigned it, and the curriculum gives no weekly load.
            ['section_id' => 1, 'subject_id' => self::ARABIC, 'teacher_id' => 8, 'weekly' => null],
            // Math of section 2 also given to teacher 99, who left the school.
            ['section_id' => 2, 'subject_id' => self::MATH, 'teacher_id' => 99, 'weekly' => 2],
        ]);

        $advice = (new TimetableAdvisor)->advise($board);
        $codes = array_map(static fn (array $f): string => $f['severity'].':'.$f['code'], $advice['findings']);

        $this->assertSame(TimetableAdvisor::BLOCKED, $advice['verdict']);
        $this->assertContains('blocker:teacher_overbooked', $codes);
        $this->assertContains('blocker:section_overbooked', $codes);
        $this->assertContains('blocker:subject_over_daily_limit', $codes);
        $this->assertContains('blocker:teacher_not_qualified', $codes);
        $this->assertContains('blocker:teacher_inactive', $codes);
        $this->assertContains('warning:weekly_load_missing', $codes);
        $this->assertContains('warning:subject_split_between_teachers', $codes);
        $this->assertSame('blocker', $advice['findings'][0]['severity'], 'blockers come first');

        $overbooked = array_values(array_filter($advice['findings'], static fn (array $f): bool => $f['code'] === 'teacher_overbooked' && $f['teacher_id'] === 8));
        $this->assertSame(32, $overbooked[0]['count']);
        $this->assertSame(25, $overbooked[0]['limit']);
        $this->assertSame(null, $advice['readiness']['sections'], 'no section list → coverage unknown');
    }

    #[Test]
    public function advisor_blocks_a_practical_when_the_day_has_no_double_slot(): void
    {
        // Every lesson is followed by a 15-minute break: no two lessons form a double.
        $periods = [
            new PeriodSnapshot(21, 1, 1, '08:00:00', '08:45:00', 1),
            new PeriodSnapshot(22, 1, 2, '09:00:00', '09:45:00', 1),
            new PeriodSnapshot(23, 1, 3, '10:00:00', '10:45:00', 1),
        ];
        $board = new TimetableBoard($periods, [], [['section_id' => 1, 'subject_id' => self::LAB, 'teacher_id' => 7, 'weekly' => 2]], ['7:'.self::LAB => true], [7], [self::LAB]);

        $advice = (new TimetableAdvisor)->advise($board);

        $this->assertSame(TimetableAdvisor::BLOCKED, $advice['verdict']);
        $this->assertSame('practical_no_double_slot', $advice['findings'][0]['code']);
        $this->assertSame(0, $advice['readiness']['school_day']);
    }

    #[Test]
    public function advisor_blocks_a_school_day_without_lessons(): void
    {
        $board = new TimetableBoard([new PeriodSnapshot(31, 1, 1, '10:00:00', '10:15:00', 2)], [], [], [], [], []);

        $advice = (new TimetableAdvisor)->advise($board);

        $this->assertSame(TimetableAdvisor::BLOCKED, $advice['verdict']);
        $this->assertSame('no_lesson_periods', $advice['findings'][0]['code']);
    }

    #[Test]
    public function workload_reports_load_days_gaps_and_runs_per_teacher(): void
    {
        $board = $this->board([
            // Teacher 8 on Sunday: periods 1 and 4 (two free periods between). Monday: a 1-2 run.
            ['id' => 1, 'section_id' => 1, 'day_of_week' => 1, 'period_id' => 11, 'subject_id' => self::MATH, 'teacher_id' => 8],
            ['id' => 2, 'section_id' => 2, 'day_of_week' => 1, 'period_id' => 15, 'subject_id' => self::MATH, 'teacher_id' => 8],
            ['id' => 3, 'section_id' => 1, 'day_of_week' => 2, 'period_id' => 11, 'subject_id' => self::MATH, 'teacher_id' => 8],
            ['id' => 4, 'section_id' => 1, 'day_of_week' => 2, 'period_id' => 12, 'subject_id' => self::MATH, 'teacher_id' => 8],
            ['id' => 5, 'section_id' => 1, 'day_of_week' => 3, 'period_id' => 14, 'subject_id' => self::LAB, 'teacher_id' => 7],
        ], [
            ['section_id' => 1, 'subject_id' => self::MATH, 'teacher_id' => 8, 'weekly' => 5],
            ['section_id' => 1, 'subject_id' => self::LAB, 'teacher_id' => 7, 'weekly' => 1],
        ]);

        $rows = (new TeacherWorkloadAnalyzer)->analyze($board);
        $byTeacher = array_column($rows, null, 'teacher_id');

        $this->assertSame(8, $rows[0]['teacher_id'], 'heaviest load first');
        $this->assertSame(5, $byTeacher[8]['required']);
        $this->assertSame(4, $byTeacher[8]['placed']);
        $this->assertSame(4, $byTeacher[8]['theory']);
        $this->assertSame(2, $byTeacher[8]['sections']);
        $this->assertSame([1 => 2, 2 => 2, 3 => 0, 4 => 0, 5 => 0], $byTeacher[8]['by_day']);
        $this->assertSame(2, $byTeacher[8]['gaps']);
        $this->assertSame(2, $byTeacher[8]['max_consecutive']);
        $this->assertSame(25, $byTeacher[8]['capacity']);
        $this->assertSame('incomplete', $byTeacher[8]['status']);
        $this->assertSame(1, $byTeacher[7]['practical']);
        $this->assertSame('ok', $byTeacher[7]['status']);
    }

    #[Test]
    public function quality_tells_feasible_complete_and_good_apart(): void
    {
        $requirements = [
            ['section_id' => 1, 'subject_id' => self::LAB, 'teacher_id' => 7, 'weekly' => 4],
            ['section_id' => 1, 'subject_id' => self::MATH, 'teacher_id' => 8, 'weekly' => 5],
        ];
        $empty = $this->board([], $requirements);
        $plan = (new TimetableAutoPlacer)->plan($empty, 1);
        $placed = array_map(static fn (array $p, int $i): array => [
            'id' => $i + 1, 'section_id' => 1, 'day_of_week' => $p['day'], 'period_id' => $p['period_id'], 'subject_id' => $p['subject_id'], 'teacher_id' => $p['teacher_id'],
        ], $plan['placements'], array_keys($plan['placements']));
        $full = $this->board($placed, $requirements);
        $scorer = new TimetableQualityScorer;

        $good = $scorer->score($full, (new TimetableAuditor)->audit($full));
        $this->assertTrue($good['feasible']);
        $this->assertSame('complete', $good['grade']);
        $this->assertSame(100, $good['metrics']['completeness']);
        $this->assertSame(100, $good['metrics']['distribution']);
        $this->assertSame(100, $good['metrics']['practical_doubles']);
        $this->assertSame(100, $good['metrics']['section_compactness']);
        $this->assertSame(['required' => 9, 'placed' => 9, 'teacher_gaps' => 0, 'section_gaps' => 0], $good['counts']);

        // One math lesson with a hole before it, the rest unplaced: feasible but incomplete and not compact.
        $partial = $this->board([
            ['id' => 1, 'section_id' => 1, 'day_of_week' => 1, 'period_id' => 11, 'subject_id' => self::MATH, 'teacher_id' => 8],
            ['id' => 2, 'section_id' => 1, 'day_of_week' => 1, 'period_id' => 15, 'subject_id' => self::MATH, 'teacher_id' => 8],
        ], $requirements);
        $weak = $scorer->score($partial, (new TimetableAuditor)->audit($partial));
        $this->assertTrue($weak['feasible']);
        $this->assertSame('incomplete', $weak['grade']);
        $this->assertSame(22, $weak['metrics']['completeness']);
        $this->assertSame(0, $weak['metrics']['section_compactness']);
        $this->assertNull($weak['metrics']['practical_doubles'], 'no practical placed → nothing to measure');
        $this->assertLessThan($good['overall'], $weak['overall']);

        $broken = $scorer->score($partial, [['severity' => 'error']]);
        $this->assertFalse($broken['feasible']);
        $this->assertSame('infeasible', $broken['grade']);
    }

    #[Test]
    public function day_runs_measure_gaps_and_the_longest_run(): void
    {
        $positions = DayRuns::positions([11, 12, 14, 15, 16], [16, 11, 14, 13]);

        $this->assertSame([0, 2, 4], $positions, 'the break (13) is not a lesson position');
        $this->assertSame(2, DayRuns::gaps($positions));
        $this->assertSame(1, DayRuns::longestRun($positions));
        $this->assertSame(3, DayRuns::longestRun([0, 1, 2, 4]));
        $this->assertSame(0, DayRuns::gaps([]));
    }

    /**
     * @param  list<array{id: int, section_id: int, day_of_week: int, period_id: int, subject_id: int, teacher_id: int}>  $schedules
     * @param  list<array{section_id: int, subject_id: int, teacher_id: int, weekly: int|null}>  $requirements
     * @param  list<int>  $sectionIds
     */
    private function board(array $schedules, array $requirements, array $sectionIds = []): TimetableBoard
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
            sectionIds: $sectionIds,
        );
    }
}
