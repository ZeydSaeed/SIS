<?php

namespace Tests\Unit\Domain;

use App\Domain\Timetable\Constraints\ConstraintRuleCatalogue;
use App\Domain\Timetable\Data\PeriodSnapshot;
use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Services\EffectiveVersionSelector;
use App\Domain\Timetable\Services\GroupSplitPlanner;
use App\Domain\Timetable\Services\StudentTimetableFilter;
use App\Domain\Timetable\Services\SubstituteRanker;
use App\Domain\Timetable\Services\TimetableAdvisor;
use App\Domain\Timetable\Services\TimetableFingerprint;
use App\Domain\Timetable\Services\TimetableVersionComparer;
use App\Domain\Timetable\Solver\ConstraintCompiler;
use App\Domain\Timetable\Solver\HeuristicTimetableSolver;
use App\Domain\Timetable\Support\TimetableSettings;
use App\Domain\Timetable\ValueObjects\GenerationMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TimetableEngineServicesTest extends TestCase
{
    #[Test]
    public function substitutes_are_free_teachers_ranked_by_qualification_class_knowledge_and_load(): void
    {
        $schedules = [
            ['id' => 1, 'section_id' => 1, 'day_of_week' => 2, 'period_id' => 11, 'subject_id' => 100, 'teacher_id' => 7],
            ['id' => 2, 'section_id' => 2, 'day_of_week' => 2, 'period_id' => 11, 'subject_id' => 100, 'teacher_id' => 9], // 9 busy then
            ['id' => 3, 'section_id' => 1, 'day_of_week' => 3, 'period_id' => 12, 'subject_id' => 101, 'teacher_id' => 8], // 8 knows section 1
        ];
        $board = $this->board($schedules, teachers: [7, 8, 9, 10], teacherSubjects: ['8:100' => true, '10:100' => true]);

        $ranked = (new SubstituteRanker)->rank($board, $schedules[0], [], [8 => 'سعيد', 10 => 'علي']);

        $this->assertSame([8, 10], array_column($ranked, 'teacher_id'));
        $this->assertSame(100, $ranked[0]['compatibility']);
        $this->assertTrue($ranked[0]['knows_section']);
        $this->assertSame('سعيد', $ranked[0]['name']);
        $this->assertSame([10], array_column((new SubstituteRanker)->rank($board, $schedules[0], [8]), 'teacher_id'), 'already covering on that date');
    }

    #[Test]
    public function versions_compare_moves_teacher_and_room_changes(): void
    {
        $a = [
            ['section_id' => 1, 'day_of_week' => 1, 'period_id' => 11, 'subject_id' => 100, 'teacher_id' => 7, 'room_id' => null],
            ['section_id' => 1, 'day_of_week' => 2, 'period_id' => 11, 'subject_id' => 101, 'teacher_id' => 8, 'room_id' => 3],
            ['section_id' => 2, 'day_of_week' => 1, 'period_id' => 12, 'subject_id' => 102, 'teacher_id' => 9, 'room_id' => null],
        ];
        $b = [
            ['section_id' => 1, 'day_of_week' => 3, 'period_id' => 12, 'subject_id' => 100, 'teacher_id' => 7, 'room_id' => null], // moved
            ['section_id' => 1, 'day_of_week' => 2, 'period_id' => 11, 'subject_id' => 101, 'teacher_id' => 10, 'room_id' => 3], // teacher
            ['section_id' => 3, 'day_of_week' => 1, 'period_id' => 11, 'subject_id' => 103, 'teacher_id' => 9, 'room_id' => null], // added
        ];

        $diff = (new TimetableVersionComparer)->compare($a, $b);

        $this->assertSame(['moved' => 1, 'teacher_changed' => 1, 'room_changed' => 0, 'added' => 1, 'removed' => 1, 'unchanged' => 0], $diff['counts']);
        $this->assertSame([1, 2, 3], $diff['sections']);
    }

    #[Test]
    public function a_section_splits_into_balanced_groups_within_capacity(): void
    {
        $planner = new GroupSplitPlanner;

        $this->assertSame([18, 18], $planner->sizes(36, 18));
        $this->assertSame([14, 13, 13], $planner->sizes(40, 18));
        $this->assertSame([[1, 3, 5], [2, 4]], $planner->deal([1, 2, 3, 4, 5], 2));
    }

    #[Test]
    public function a_student_sees_whole_class_lessons_and_only_their_own_groups(): void
    {
        $lessons = [
            ['day_of_week' => 1, 'period_id' => 12, 'group_id' => null, 'subject_id' => 100],
            ['day_of_week' => 1, 'period_id' => 11, 'group_id' => 501, 'subject_id' => 200],
            ['day_of_week' => 1, 'period_id' => 11, 'group_id' => 502, 'subject_id' => 201],
            ['day_of_week' => 2, 'period_id' => 11, 'group_id' => 601, 'subject_id' => 300],
        ];

        $result = (new StudentTimetableFilter)->filter($lessons, [501], [501 => 50, 502 => 50, 601 => 60]);

        $this->assertSame([200, 100], array_column($result['lessons'], 'subject_id'));
        $this->assertSame([60], $result['unassigned_divisions']);
    }

    #[Test]
    public function the_effective_version_is_the_latest_published_one_covering_the_date(): void
    {
        $versions = [
            ['id' => 1, 'status' => 6, 'effective_from' => '2026-09-01', 'effective_to' => '2026-10-31', 'published_at' => '2026-08-30'],
            ['id' => 2, 'status' => 5, 'effective_from' => '2026-11-01', 'effective_to' => null, 'published_at' => '2026-10-20'],
            ['id' => 3, 'status' => 3, 'effective_from' => null, 'effective_to' => null, 'published_at' => null],
        ];
        $selector = new EffectiveVersionSelector;

        $this->assertSame(1, $selector->select($versions, '2026-10-07'), 'superseded versions still govern their own dates');
        $this->assertSame(2, $selector->select($versions, '2026-12-01'));
        $this->assertNull($selector->select($versions, '2026-08-01'));
    }

    #[Test]
    public function the_fingerprint_changes_with_the_sources_not_with_the_grid(): void
    {
        $requirements = [['section_id' => 1, 'subject_id' => 100, 'teacher_id' => 7, 'weekly' => 3]];
        $fingerprint = new TimetableFingerprint;
        $base = $fingerprint->of($this->board([], $requirements));

        $withLesson = $fingerprint->of($this->board([['id' => 1, 'section_id' => 1, 'day_of_week' => 1, 'period_id' => 11, 'subject_id' => 100, 'teacher_id' => 7]], $requirements));
        $moreHours = $fingerprint->of($this->board([], [['section_id' => 1, 'subject_id' => 100, 'teacher_id' => 7, 'weekly' => 4]]));

        $this->assertSame($base, $withLesson);
        $this->assertNotSame($base, $moreHours);
    }

    #[Test]
    public function the_advisor_blocks_an_activity_no_room_can_hold_and_an_overbooked_workshop(): void
    {
        $activity = static fn (int $id, int $section, int $weekly): array => [
            'id' => $id, 'subject_id' => 200, 'activity_type' => 2, 'weekly' => $weekly, 'block' => 2, 'distribution' => null,
            'room_id' => null, 'room_type' => null, 'workshop_id' => 40, 'week_pattern' => 0,
            'targets' => [['section_id' => $section, 'group_id' => null]], 'teachers' => [['teacher_id' => 7, 'role' => 1, 'sessions' => null]],
        ];
        $board = $this->board([], [], teachers: [7], teacherSubjects: ['7:200' => true],
            activities: [$activity(1, 1, 2), $activity(2, 2, 14), $activity(3, 3, 14)],
            workshops: [40 => ['id' => 40, 'capacity' => 20, 'safety_capacity' => 18, 'room_id' => null]],
            sectionInfo: [1 => ['class_id' => 1, 'grade_level_id' => 1, 'branch_ids' => [], 'department_ids' => [], 'students' => 30],
                2 => ['class_id' => 1, 'grade_level_id' => 1, 'branch_ids' => [], 'department_ids' => [], 'students' => 15],
                3 => ['class_id' => 1, 'grade_level_id' => 1, 'branch_ids' => [], 'department_ids' => [], 'students' => 15]]);

        $advice = (new TimetableAdvisor)->advise($board);
        $codes = array_column($advice['findings'], 'code');

        $this->assertSame(TimetableAdvisor::BLOCKED, $advice['verdict']);
        $this->assertContains('activity_blocked', $codes);
        $blocked = $advice['findings'][array_search('activity_blocked', $codes, true)];
        $this->assertSame('workshop_capacity', $blocked['detail']);
        $this->assertContains('facility_overbooked', $codes, '28 workshop periods > 5 × 4 open');
    }

    #[Test]
    public function move_suggestions_offer_free_moves_and_same_length_swaps_never_a_hard_break(): void
    {
        $schedules = [
            ['id' => 1, 'section_id' => 1, 'day_of_week' => 1, 'period_id' => 11, 'subject_id' => 100, 'teacher_id' => 7],
            ['id' => 2, 'section_id' => 1, 'day_of_week' => 1, 'period_id' => 12, 'subject_id' => 101, 'teacher_id' => 8],
        ];
        $board = $this->board($schedules, [
            ['section_id' => 1, 'subject_id' => 100, 'teacher_id' => 7, 'weekly' => 1],
            ['section_id' => 1, 'subject_id' => 101, 'teacher_id' => 8, 'weekly' => 1],
        ], teachers: [7, 8], teacherSubjects: ['7:100' => true, '8:101' => true]);
        $problem = (new ConstraintCompiler)->compile($board, GenerationMode::Optimize, ['section_ids' => [1]]);
        $card = array_key_first(array_filter($problem->cards, static fn ($c): bool => $c->subjectId === 100));

        $options = (new HeuristicTimetableSolver)->suggestMoves($problem, $card, 50);

        $kinds = array_count_values(array_column($options, 'kind'));
        $this->assertSame(1, $kinds['swap'] ?? 0, 'the math lesson can trade places with the next one');
        $this->assertGreaterThan(0, $kinds['move'] ?? 0);
        foreach ($options as $o) {
            $this->assertLessThanOrEqual(0, $o['hard']);
        }
    }

    #[Test]
    public function the_rule_catalogue_validates_scope_and_params(): void
    {
        $this->assertSame([], ConstraintRuleCatalogue::validate('teacher_max_per_day', ['teacher_id' => 7], ['max' => 5], 2));
        $this->assertSame(['timetable.rule_scope_not_allowed'], ConstraintRuleCatalogue::validate('teacher_max_per_day', ['section_id' => 1], ['max' => 5], 2));
        $this->assertSame(['timetable.rule_scope_required'], ConstraintRuleCatalogue::validate('activity_before', ['activity_id' => 1], [], 1));
        $this->assertSame(['timetable.rule_params_invalid'], ConstraintRuleCatalogue::validate('forbidden_slots', ['subject_id' => 1], [], 3));
        $this->assertSame(['timetable.rule_type_unknown'], ConstraintRuleCatalogue::validate('nope', [], [], 3));
        $this->assertSame(['lessons' => [1, 7]], ConstraintRuleCatalogue::normaliseParams('forbidden_slots', ['lessons' => ['7', 1, 7], 'other' => 3]));
        $this->assertGreaterThan(ConstraintRuleCatalogue::specificity(['branch_id' => 1]), ConstraintRuleCatalogue::specificity(['section_id' => 1]));
    }

    #[Test]
    public function settings_drive_the_week(): void
    {
        $settings = new TimetableSettings(days: [1, 2, 3, 4, 5, 6], cycleWeeks: 2, maxTeacherPerDay: 5);

        $this->assertSame([1, 2], $settings->weeksOf(null));
        $this->assertSame([2], $settings->weeksOf(2));
        $this->assertSame(30, $settings->teacherCapacity(7));
    }

    private function board(array $schedules, array $requirements = [], array $teachers = [], array $teacherSubjects = [], array $activities = [], array $workshops = [], array $sectionInfo = []): TimetableBoard
    {
        $periods = [
            new PeriodSnapshot(11, 1, 1, '08:00:00', '08:45:00', 1),
            new PeriodSnapshot(12, 1, 2, '08:45:00', '09:30:00', 1),
            new PeriodSnapshot(13, 1, 3, '09:30:00', '10:15:00', 1),
            new PeriodSnapshot(14, 1, 4, '10:15:00', '11:00:00', 1),
        ];

        return new TimetableBoard($periods, $schedules, $requirements, $teacherSubjects, $teachers, [], [], null, $activities, [], [], [], [], $workshops, $sectionInfo);
    }
}
