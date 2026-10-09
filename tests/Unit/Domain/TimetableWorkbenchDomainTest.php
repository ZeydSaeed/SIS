<?php

namespace Tests\Unit\Domain;

use App\Domain\Timetable\Data\PeriodSnapshot;
use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Services\TimetableAdvisor;
use App\Domain\Timetable\Services\TimetableAuditor;
use App\Domain\Timetable\Solver\GenerationStrategy;
use App\Domain\Timetable\Solver\SolverResult;
use App\Domain\Timetable\Support\TimetableDisplaySettings;
use App\Domain\Timetable\Testing\DataChecks;
use App\Domain\Timetable\Testing\DayChecks;
use App\Domain\Timetable\Testing\PlacementChecks;
use App\Domain\Timetable\Testing\RemedyFinder;
use App\Domain\Timetable\Testing\TestIssue;
use App\Domain\Timetable\Testing\TimetableTestRunner;
use App\Domain\Timetable\ValueObjects\GenerationMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** «اختبار الجدول», «تعقيد الإنشاء / مستوى القيود» and «تنسيق الجدول» as pure Domain rules. */
final class TimetableWorkbenchDomainTest extends TestCase
{
    #[Test]
    public function complexity_changes_the_search_and_the_level_changes_what_is_hard(): void
    {
        $normal = GenerationStrategy::from([]);
        $huge = GenerationStrategy::from(['complexity' => 'huge', 'constraint_level' => 'strict']);

        $this->assertCount(1, $normal->attempts(7));
        $this->assertSame(10.0, $normal->budget());
        $attempts = $huge->attempts(7);
        $this->assertCount(3, $attempts);
        $this->assertSame([7, 8, 9], array_map(static fn ($o): int => $o->seed, $attempts));
        $this->assertGreaterThan($normal->attempts(1)[0]->maxIterations, $attempts[0]->maxIterations);
        $this->assertGreaterThan($normal->attempts(1)[0]->ejectionAttempts, $attempts[0]->ejectionAttempts);

        $this->assertSame(GenerationMode::Strict, $huge->mode(GenerationMode::Balanced));
        $this->assertSame(GenerationMode::Relaxed, GenerationStrategy::from(['constraint_level' => 'relaxed'])->mode(GenerationMode::Balanced));
        $this->assertSame(GenerationMode::Optimize, $huge->mode(GenerationMode::Optimize), 'optimize keeps the grid');
        $this->assertSame(GenerationMode::Repair, GenerationStrategy::from([])->mode(GenerationMode::Repair));

        // Basic drops the soft user rules; other levels keep all of them.
        $rules = [['id' => 1, 'rule_type' => 'a', 'priority' => 1, 'scope' => [], 'params' => []], ['id' => 2, 'rule_type' => 'b', 'priority' => 5, 'scope' => [], 'params' => []]];
        $this->assertSame([1], array_column(GenerationStrategy::from(['constraint_level' => 'basic'])->rules($rules, GenerationMode::Balanced), 'id'));
        $this->assertCount(2, $huge->rules($rules, GenerationMode::Strict));
    }

    #[Test]
    public function the_best_attempt_has_fewer_hard_violations_then_fewer_unplaced_then_lower_penalty(): void
    {
        $result = static fn (int $hard, int $unplaced, int $soft): SolverResult => new SolverResult([], array_fill_keys(array_map('strval', range(1, max(1, $unplaced))), ['reasons' => [], 'suggestions' => []]) + ($unplaced === 0 ? [] : []), [], $hard, $soft, 0, 0, false);

        $this->assertTrue(GenerationStrategy::better($result(0, 1, 900), null));
        $this->assertTrue(GenerationStrategy::better($result(0, 1, 100), $result(0, 1, 900)));
        $this->assertFalse(GenerationStrategy::better($result(1, 1, 0), $result(0, 1, 900)));
    }

    #[Test]
    public function the_test_report_classifies_explains_and_proposes_remedies(): void
    {
        $periods = [
            new PeriodSnapshot(1, 1, 1, '08:00:00', '08:45:00', 1),
            new PeriodSnapshot(2, 1, 2, '08:45:00', '09:30:00', 1),
            new PeriodSnapshot(3, 1, 3, '09:30:00', '10:15:00', 1),
            new PeriodSnapshot(4, 1, 4, '10:15:00', '10:25:00', 2),
        ];
        $schedules = [
            ['id' => 11, 'section_id' => 100, 'day_of_week' => 1, 'period_id' => 1, 'subject_id' => 7, 'teacher_id' => 50, 'room_id' => 9],
            ['id' => 12, 'section_id' => 101, 'day_of_week' => 1, 'period_id' => 1, 'subject_id' => 7, 'teacher_id' => 51, 'room_id' => 9],
        ];
        $board = new TimetableBoard(
            $periods, $schedules,
            [['section_id' => 100, 'subject_id' => 7, 'teacher_id' => 50, 'weekly' => 1], ['section_id' => 101, 'subject_id' => 7, 'teacher_id' => 51, 'weekly' => 1]],
            ['50:7' => true, '51:7' => true], [50, 51], [7], [100, 101],
            rooms: [9 => ['id' => 9, 'capacity' => 20, 'room_type' => 1], 10 => ['id' => 10, 'capacity' => 40, 'room_type' => 2]],
            sectionInfo: [100 => ['class_id' => 1, 'grade_level_id' => null, 'branch_ids' => [], 'department_ids' => [], 'students' => 30], 101 => ['class_id' => 1, 'grade_level_id' => null, 'branch_ids' => [], 'department_ids' => [], 'students' => 10]],
        );
        $runner = new TimetableTestRunner(new TimetableAdvisor, new TimetableAuditor, new PlacementChecks, new DayChecks, new DataChecks);
        $display = ['teachers' => [50 => ['id' => 50, 'abbreviation' => 'ع', 'suggested' => 'علي', 'short' => 'ع'], 51 => ['id' => 51, 'abbreviation' => null, 'suggested' => 'ع', 'short' => 'ع']]];
        $report = $runner->run($board, $display);
        $byCode = [];
        foreach ($report['issues'] as $issue) {
            $byCode[$issue['code']][] = $issue;
        }

        // The room taken twice: an error with a free practical room and free slots as safe remedies.
        $clash = $byCode['room_double_booked'][0];
        $this->assertSame(TestIssue::ERROR, $clash['severity']);
        $this->assertSame('rooms', $clash['category']);
        $this->assertSame([11, 12], $clash['schedule_ids']);
        $codes = array_column($clash['fixes'], 'code');
        $this->assertContains('change_room', $codes);
        $this->assertContains('move_lesson', $codes);
        $this->assertTrue($clash['fixes'][0]['safe']);

        // 30 students in a 20-seat room, a practical lesson in a classroom, a break at the end of the day,
        // two teachers printed with the same abbreviation.
        $this->assertSame(30, $byCode['room_capacity_exceeded'][0]['count']);
        $this->assertArrayHasKey('break_at_day_edge', $byCode);
        $this->assertSame('data', $byCode['duplicate_abbreviation'][0]['category']);
        $this->assertSame('ready_with_issues', $report['verdict']);
        $this->assertTrue($report['can_generate']);
        $this->assertSame(count($report['issues']), array_sum($report['counts']));

        // Marks label an issue; keys are stable between runs.
        $again = $runner->run($board, $display, [$clash['key'] => 2]);
        $marked = array_values(array_filter($again['issues'], static fn (array $i): bool => $i['key'] === $clash['key']));
        $this->assertSame('review', $marked[0]['mark']);
    }

    #[Test]
    public function remedies_never_propose_a_busy_slot_or_room(): void
    {
        $periods = [new PeriodSnapshot(1, 1, 1, '08:00:00', '08:45:00', 1), new PeriodSnapshot(2, 1, 2, '08:45:00', '09:30:00', 1)];
        $lesson = ['id' => 1, 'section_id' => 100, 'day_of_week' => 1, 'period_id' => 1, 'subject_id' => 7, 'teacher_id' => 50, 'room_id' => null];
        $other = ['id' => 2, 'section_id' => 101, 'day_of_week' => 1, 'period_id' => 2, 'subject_id' => 7, 'teacher_id' => 50, 'room_id' => 9];
        $board = new TimetableBoard($periods, [$lesson, $other], [], [], [50], [], [100, 101], rooms: [9 => ['id' => 9, 'capacity' => 30, 'room_type' => 2]]);
        $finder = new RemedyFinder($board);

        $slots = array_map(static fn (array $s): string => $s['day'].':'.$s['period_id'], $finder->freeSlots($lesson, 20));
        $this->assertNotContains('1:2', $slots, 'the teacher teaches section 101 then');
        $this->assertContains('2:2', $slots);
        $this->assertSame([], $finder->freeRooms($other + ['room_id' => null], 10, true), 'room 9 is the one in use at that slot');
    }

    #[Test]
    public function display_settings_fall_back_to_defaults_for_unknown_values(): void
    {
        $settings = TimetableDisplaySettings::from(['layout' => 'columns', 'font_family' => 'Comic Sans', 'font_scale' => 400, 'subject_weight' => '600', 'column_width' => 20, 'fields' => ['room' => 1, 'x' => true]])->toArray();

        $this->assertSame('columns', $settings['layout']);
        $this->assertSame('segoe', $settings['font_family']);
        $this->assertSame(100, $settings['font_scale']);
        $this->assertSame(600, $settings['subject_weight']);
        $this->assertSame(0, $settings['column_width']);
        $this->assertTrue($settings['fields']['room']);
        $this->assertArrayNotHasKey('x', $settings['fields']);
        $this->assertSame(TimetableDisplaySettings::defaults()->toArray(), TimetableDisplaySettings::from(null)->toArray());
    }
}
