<?php

namespace Tests\Unit\Domain;

use App\Domain\Timetable\Data\PeriodSnapshot;
use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Services\TimetableAuditor;
use App\Domain\Timetable\Solver\ConstraintCompiler;
use App\Domain\Timetable\Solver\HeuristicTimetableSolver;
use App\Domain\Timetable\Solver\PlacementRows;
use App\Domain\Timetable\Solver\SolverOptions;
use App\Domain\Timetable\Solver\SolverProblem;
use App\Domain\Timetable\Solver\SolverResult;
use App\Domain\Timetable\ValueObjects\GenerationMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The engine end to end on pure data: compile → solve → rows → audit.
 * School day: lessons 1 2 3 [15' break] 4 5 6 7 (ids 11–13, 14–17); doubles inside each half.
 */
final class TimetableSolverTest extends TestCase
{
    private const LESSONS = [11, 12, 13, 14, 15, 16, 17];

    #[Test]
    public function a_school_without_configuration_gets_a_complete_valid_timetable(): void
    {
        // 5 sections × 6 subjects × 5 a week = 30 of 35 slots; subject j: teacher 2j in sections 1–3, 2j+1 in 4–5.
        $requirements = [];
        $teacherSubjects = [];
        for ($s = 1; $s <= 5; $s++) {
            for ($j = 1; $j <= 6; $j++) {
                $teacher = $s <= 3 ? 2 * $j : 2 * $j + 1;
                $requirements[] = ['section_id' => $s, 'subject_id' => 100 + $j, 'teacher_id' => $teacher, 'weekly' => 5];
                $teacherSubjects[$teacher.':'.(100 + $j)] = true;
            }
        }
        $board = $this->board([], $requirements, teacherSubjects: $teacherSubjects, teachers: range(2, 13), sectionIds: [1, 2, 3, 4, 5]);

        [$problem, $result] = $this->solve($board, GenerationMode::Balanced);

        $this->assertSame([], $result->unplaced);
        $this->assertSame(0, $result->hardViolations);
        $this->assertCount(150, PlacementRows::from($problem, $result));
        $this->assertNoAuditErrors($board, $problem, $result);
    }

    #[Test]
    public function groups_of_one_division_share_a_slot_and_a_joined_lesson_keeps_its_teacher_busy_once(): void
    {
        $groups = [
            501 => ['id' => 501, 'division_id' => 50, 'section_id' => 1, 'name' => 'A', 'student_count' => 15],
            502 => ['id' => 502, 'division_id' => 50, 'section_id' => 1, 'name' => 'B', 'student_count' => 15],
        ];
        $activities = [
            $this->activity(1, 200, 4, block: 2, targets: [[1, 501]], teachers: [[7, 1, null]]),
            $this->activity(2, 201, 4, block: 2, targets: [[1, 502]], teachers: [[8, 1, null]]),
            // PE joined: sections 1 and 2 together, one teacher.
            $this->activity(3, 202, 3, targets: [[1, null], [2, null]], teachers: [[9, 1, null]]),
        ];
        $board = $this->board([], [], activities: $activities, groups: $groups, teachers: [7, 8, 9], teacherSubjects: ['7:200' => true, '8:201' => true, '9:202' => true]);

        [$problem, $result] = $this->solve($board, GenerationMode::Balanced);

        $this->assertSame([], $result->unplaced);
        $rows = PlacementRows::from($problem, $result);
        $pe = array_values(array_filter($rows, static fn (array $r): bool => $r['subject_id'] === 202));
        $this->assertCount(6, $pe, '3 lessons × 2 sections');
        $this->assertCount(3, array_filter($pe, static fn (array $r): bool => $r['is_lead']));
        $this->assertNoAuditErrors($board, $problem, $result);
    }

    #[Test]
    public function a_co_teacher_attends_only_their_sessions(): void
    {
        $board = $this->board([], [], activities: [$this->activity(1, 300, 5, targets: [[1, null]], teachers: [[7, 1, null], [8, 2, 3]])], teachers: [7, 8], teacherSubjects: ['7:300' => true]);

        [$problem, $result] = $this->solve($board, GenerationMode::Balanced);

        $this->assertSame([], $result->unplaced);
        $withCo = array_filter(PlacementRows::from($problem, $result), static fn (array $r): bool => $r['co_teacher_id'] === 8);
        $this->assertCount(3, $withCo, 'teacher 8 co-teaches 3 of the 5 lessons');
    }

    #[Test]
    public function an_unavailable_teacher_and_a_closed_workshop_are_never_used(): void
    {
        $availability = [];
        foreach (self::LESSONS as $period) {
            $availability[] = ['teacher_id' => 7, 'room_id' => null, 'section_id' => null, 'workshop_id' => null, 'day' => 1, 'period_id' => $period, 'week_no' => null, 'kind' => 1];
        }
        foreach ([11, 12, 13] as $period) {
            $availability[] = ['teacher_id' => null, 'room_id' => null, 'section_id' => null, 'workshop_id' => 40, 'day' => 2, 'period_id' => $period, 'week_no' => null, 'kind' => 1];
        }
        $board = $this->board([], [['section_id' => 1, 'subject_id' => 100, 'teacher_id' => 7, 'weekly' => 5]],
            activities: [$this->activity(1, 200, 4, block: 2, targets: [[2, null]], teachers: [[8, 1, null]], workshop: 40)],
            availability: $availability,
            workshops: [40 => ['id' => 40, 'capacity' => 20, 'safety_capacity' => 18, 'room_id' => null]],
            sectionInfo: [1 => $this->info(30), 2 => $this->info(16)],
            teachers: [7, 8], teacherSubjects: ['7:100' => true, '8:200' => true]);

        [$problem, $result] = $this->solve($board, GenerationMode::Balanced);

        $this->assertSame([], $result->unplaced);
        foreach (PlacementRows::from($problem, $result) as $row) {
            if ($row['teacher_id'] === 7) {
                $this->assertNotSame(1, $row['day_of_week'], 'teacher 7 is off on Sunday');
            }
            if ($row['subject_id'] === 200) {
                $this->assertFalse($row['day_of_week'] === 2 && in_array($row['period_id'], [11, 12, 13], true), 'workshop maintenance');
            }
        }
    }

    #[Test]
    public function a_class_bigger_than_the_workshop_is_blocked_with_the_reason(): void
    {
        $board = $this->board([], [],
            activities: [$this->activity(1, 200, 2, block: 2, targets: [[1, null]], teachers: [[8, 1, null]], workshop: 40)],
            workshops: [40 => ['id' => 40, 'capacity' => 20, 'safety_capacity' => 18, 'room_id' => null]],
            sectionInfo: [1 => $this->info(36)], teachers: [8], teacherSubjects: ['8:200' => true]);

        [, $result] = $this->solve($board, GenerationMode::Balanced);

        $this->assertCount(1, $result->unplaced);
        $this->assertSame(['workshop_capacity' => 1], array_values($result->unplaced)[0]['reasons']);
    }

    #[Test]
    public function a_locked_lesson_stays_and_counts_towards_the_weekly_load(): void
    {
        $locked = ['id' => 900, 'section_id' => 1, 'day_of_week' => 3, 'period_id' => 11, 'subject_id' => 100, 'teacher_id' => 7, 'locked' => true];
        $board = $this->board([$locked], [['section_id' => 1, 'subject_id' => 100, 'teacher_id' => 7, 'weekly' => 4]], teachers: [7], teacherSubjects: ['7:100' => true]);

        [$problem, $result] = $this->solve($board, GenerationMode::Balanced);

        $this->assertCount(3, $problem->cards, '4 a week − 1 locked');
        $this->assertSame([], $result->unplaced);
        foreach (PlacementRows::from($problem, $result) as $row) {
            $this->assertFalse($row['day_of_week'] === 3 && $row['period_id'] === 11, 'the locked slot is taken');
        }
    }

    #[Test]
    public function an_impossible_load_is_explained_not_just_failed(): void
    {
        // Teacher 7: 40 lessons, the strict week holds 5 × 6 = 30.
        $requirements = [];
        for ($s = 1; $s <= 8; $s++) {
            $requirements[] = ['section_id' => $s, 'subject_id' => 100, 'teacher_id' => 7, 'weekly' => 5];
        }
        $board = $this->board([], $requirements, teachers: [7], teacherSubjects: ['7:100' => true]);

        [, $result] = $this->solve($board, GenerationMode::Strict);

        $this->assertNotSame([], $result->unplaced);
        $this->assertFalse($result->feasible());
        $first = array_values($result->unplaced)[0];
        $this->assertNotSame([], $first['reasons']);
        $this->assertTrue(isset($first['reasons']['teacher_busy']) || isset($first['reasons']['rule:teacher_max_per_day']));
        $this->assertNotSame([], $first['suggestions']);
    }

    #[Test]
    public function repair_moves_only_what_a_teacher_absence_breaks(): void
    {
        $requirements = [
            ['section_id' => 1, 'subject_id' => 100, 'teacher_id' => 7, 'weekly' => 5],
            ['section_id' => 1, 'subject_id' => 101, 'teacher_id' => 8, 'weekly' => 5],
        ];
        $board = $this->board([], $requirements, teachers: [7, 8], teacherSubjects: ['7:100' => true, '8:101' => true]);
        [$problem, $result] = $this->solve($board, GenerationMode::Balanced);
        $schedules = $this->asSchedules(PlacementRows::from($problem, $result));
        $teacher8Before = array_values(array_filter($schedules, static fn (array $s): bool => $s['teacher_id'] === 8));

        $absence = array_map(static fn (int $p): array => ['teacher_id' => 7, 'room_id' => null, 'section_id' => null, 'workshop_id' => null, 'day' => 2, 'period_id' => $p, 'week_no' => null, 'kind' => 1], self::LESSONS);
        $after = $this->board($schedules, $requirements, availability: $absence, teachers: [7, 8], teacherSubjects: ['7:100' => true, '8:101' => true]);
        [$problem2, $repaired] = $this->solve($after, GenerationMode::Repair);

        $this->assertSame([], $repaired->unplaced);
        $rows = PlacementRows::from($problem2, $repaired);
        $this->assertSame([], array_filter($rows, static fn (array $r): bool => $r['teacher_id'] === 7 && $r['day_of_week'] === 2));
        $this->assertCount(5, array_filter($rows, static fn (array $r): bool => $r['teacher_id'] === 7));
        $moved = 0;
        foreach ($teacher8Before as $s) {
            $still = array_filter($rows, static fn (array $r): bool => $r['teacher_id'] === 8 && $r['day_of_week'] === $s['day_of_week'] && $r['period_id'] === $s['period_id']);
            $moved += $still === [] ? 1 : 0;
        }
        $this->assertLessThanOrEqual(2, $moved, 'repair keeps the unaffected teacher where it was');
    }

    #[Test]
    public function the_most_specific_rule_wins_and_rules_say_where_they_come_from(): void
    {
        $rules = [
            ['id' => 1, 'rule_type' => 'teacher_max_per_day', 'priority' => 1, 'scope' => [], 'params' => ['max' => 3]],
            ['id' => 2, 'rule_type' => 'teacher_max_per_day', 'priority' => 1, 'scope' => ['teacher_id' => 7], 'params' => ['max' => 5]],
        ];
        $board = $this->board([], [
            ['section_id' => 1, 'subject_id' => 100, 'teacher_id' => 7, 'weekly' => 2],
            ['section_id' => 1, 'subject_id' => 101, 'teacher_id' => 8, 'weekly' => 2],
        ], rules: $rules, teachers: [7, 8], teacherSubjects: ['7:100' => true, '8:101' => true]);

        $problem = (new ConstraintCompiler)->compile($board, GenerationMode::Balanced);
        $max = static fn (int $teacher): array => array_values(array_filter($problem->teacherRules[$teacher], static fn (array $r): bool => $r['type'] === 'teacher_max_per_day'))[0];

        $this->assertSame(5, $max(7)['params']['max']);
        $this->assertSame('teacher', $max(7)['source']);
        $this->assertSame(3, $max(8)['params']['max']);
        $this->assertSame('school', $max(8)['source']);
        $this->assertTrue($max(8)['hard'], 'CRITICAL is hard in every mode');
    }

    #[Test]
    public function the_vocational_scenario_theory_before_practice_split_groups_workshop_and_lab(): void
    {
        // §147: Electrical, 2nd year, sections A (1) / B (2) of 36; workshop 18; lab room 30 (type 3, capacity 20).
        $groups = [];
        foreach ([[1, 61, 611, 612], [2, 62, 621, 622]] as [$section, $division, $g1, $g2]) {
            $groups[$g1] = ['id' => $g1, 'division_id' => $division, 'section_id' => $section, 'name' => '1', 'student_count' => 18];
            $groups[$g2] = ['id' => $g2, 'division_id' => $division, 'section_id' => $section, 'name' => '2', 'student_count' => 18];
        }
        $activities = [];
        $rules = [];
        $id = 1;
        foreach ([[1, 611, 612, 21], [2, 621, 622, 22]] as [$section, $g1, $g2, $practiceTeacher]) {
            $theory = $id++;
            $activities[] = $this->activity($theory, 400, 3, targets: [[$section, null]], teachers: [[20, 1, null]]);
            foreach ([$g1, $g2] as $group) {
                $practice = $id++;
                $activities[] = $this->activity($practice, 401, 4, block: 2, targets: [[$section, $group]], teachers: [[$practiceTeacher, 1, null]], workshop: 40);
                $activities[] = $this->activity($id++, 402, 2, block: 2, targets: [[$section, $group]], teachers: [[23, 1, null]], roomType: 3);
                $rules[] = ['id' => $practice, 'rule_type' => 'activity_before', 'priority' => 1, 'scope' => ['activity_id' => $theory, 'other_activity_id' => $practice], 'params' => []];
            }
        }
        $board = $this->board([], [], activities: $activities, groups: $groups, rules: $rules,
            rooms: [30 => ['id' => 30, 'capacity' => 20, 'room_type' => 3]],
            workshops: [40 => ['id' => 40, 'capacity' => 20, 'safety_capacity' => 18, 'room_id' => null]],
            sectionInfo: [1 => $this->info(36), 2 => $this->info(36)],
            teachers: [20, 21, 22, 23], teacherSubjects: ['20:400' => true, '21:401' => true, '22:401' => true, '23:402' => true]);

        [$problem, $result] = $this->solve($board, GenerationMode::Balanced);

        $this->assertSame([], $result->unplaced);
        $this->assertSame(0, $result->hardViolations, 'theory before practice is CRITICAL');
        $rows = PlacementRows::from($problem, $result);
        $workshopSlots = [];
        foreach ($rows as $row) {
            if ($row['subject_id'] === 401) {
                $slot = $row['day_of_week'].':'.$row['period_id'];
                $this->assertArrayNotHasKey($slot, $workshopSlots, 'one group in the workshop at a time');
                $workshopSlots[$slot] = true;
            }
            if ($row['subject_id'] === 402) {
                $this->assertSame(30, $row['room_id'], 'lab in the lab room');
            }
        }
        $this->assertCount(16, $workshopSlots);
        $this->assertNoAuditErrors($board, $problem, $result);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return array{0: SolverProblem, 1: SolverResult} */
    private function solve(TimetableBoard $board, GenerationMode $mode): array
    {
        $problem = (new ConstraintCompiler)->compile($board, $mode);
        $result = (new HeuristicTimetableSolver)->solve($problem, new SolverOptions(seed: 7, timeBudgetSeconds: 5.0, maxIterations: 1500));

        return [$problem, $result];
    }

    private function assertNoAuditErrors(TimetableBoard $board, SolverProblem $problem, SolverResult $result): void
    {
        $audited = new TimetableBoard($board->periods, $this->asSchedules(PlacementRows::from($problem, $result)), $board->requirements,
            $board->teacherSubjects, $board->activeTeacherIds, $board->practicalSubjectIds, $board->sectionIds, $board->settings,
            $board->activities, $board->groups);
        $errors = array_filter((new TimetableAuditor)->audit($audited), static fn (array $i): bool => $i['severity'] === 'error'
            && $i['code'] !== 'teacher_not_assigned');
        $this->assertSame([], array_values($errors));
    }

    /** @return list<array<string, mixed>> */
    private function asSchedules(array $rows): array
    {
        $schedules = [];
        $leadIds = [];
        foreach ($rows as $n => $row) {
            if ($row['is_lead']) {
                $leadIds[$row['lead_key']] = 1000 + $n;
            }
        }
        foreach ($rows as $n => $row) {
            $schedules[] = [
                'id' => 1000 + $n, 'section_id' => $row['section_id'], 'day_of_week' => $row['day_of_week'], 'period_id' => $row['period_id'],
                'subject_id' => $row['subject_id'], 'teacher_id' => $row['teacher_id'], 'room_id' => $row['room_id'], 'group_id' => $row['group_id'],
                'week_no' => $row['week_no'], 'co_teacher_id' => $row['co_teacher_id'], 'joined_to' => $row['is_lead'] ? null : $leadIds[$row['lead_key']],
                'activity_id' => $row['activity_id'],
            ];
        }

        return $schedules;
    }

    /**
     * @param  list<array{0: int, 1: int|null}>  $targets
     * @param  list<array{0: int, 1: int, 2: int|null}>  $teachers  [teacher, role, sessions]
     */
    private function activity(int $id, int $subject, int $weekly, int $block = 1, array $targets = [], array $teachers = [], ?int $workshop = null, ?int $roomType = null): array
    {
        return [
            'id' => $id, 'subject_id' => $subject, 'activity_type' => 1, 'weekly' => $weekly, 'block' => $block, 'distribution' => null,
            'room_id' => null, 'room_type' => $roomType, 'workshop_id' => $workshop, 'week_pattern' => 0,
            'targets' => array_map(static fn (array $t): array => ['section_id' => $t[0], 'group_id' => $t[1]], $targets),
            'teachers' => array_map(static fn (array $t): array => ['teacher_id' => $t[0], 'role' => $t[1], 'sessions' => $t[2]], $teachers),
        ];
    }

    private function info(int $students): array
    {
        return ['class_id' => 1, 'grade_level_id' => 2, 'branch_ids' => [1], 'department_ids' => [1], 'students' => $students];
    }

    private function board(
        array $schedules,
        array $requirements,
        array $activities = [],
        array $groups = [],
        array $availability = [],
        array $rules = [],
        array $rooms = [],
        array $workshops = [],
        array $sectionInfo = [],
        array $teachers = [],
        array $teacherSubjects = [],
        array $sectionIds = [],
    ): TimetableBoard {
        $periods = [
            new PeriodSnapshot(11, 1, 1, '08:00:00', '08:45:00', 1),
            new PeriodSnapshot(12, 1, 2, '08:45:00', '09:30:00', 1),
            new PeriodSnapshot(13, 1, 3, '09:30:00', '10:15:00', 1),
            new PeriodSnapshot(20, 1, 4, '10:15:00', '10:30:00', 2),
            new PeriodSnapshot(14, 1, 5, '10:30:00', '11:15:00', 1),
            new PeriodSnapshot(15, 1, 6, '11:15:00', '12:00:00', 1),
            new PeriodSnapshot(16, 1, 7, '12:00:00', '12:45:00', 1),
            new PeriodSnapshot(17, 1, 8, '12:45:00', '13:30:00', 1),
        ];

        return new TimetableBoard($periods, $schedules, $requirements, $teacherSubjects, $teachers, [], $sectionIds, null,
            $activities, $groups, $availability, $rules, $rooms, $workshops, $sectionInfo);
    }
}
