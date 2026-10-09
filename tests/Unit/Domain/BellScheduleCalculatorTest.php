<?php

namespace Tests\Unit\Domain;

use App\Domain\Timetable\Data\PeriodSnapshot;
use App\Domain\Timetable\Services\BellScheduleCalculator;
use App\Domain\Timetable\ValueObjects\PeriodPresentation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** «الاستراحات وإعدادات الحصص»: every operation re-times the rest of the day automatically («تزحيف»). */
final class BellScheduleCalculatorTest extends TestCase
{
    #[Test]
    public function inserting_a_break_after_the_fourth_lesson_moves_the_rest_later(): void
    {
        $plan = (new BellScheduleCalculator)->insertBreak($this->day(), 14, 20, new PeriodPresentation(name: 'استراحة الطلاب بعد الحصة الرابعة'));

        $this->assertIsArray($plan);
        $this->assertSame(['08:00', '08:45', '09:30', '10:15', '11:00', '11:20', '12:05'], array_column($plan, 'start'));
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], array_column($plan, 'number'));
        $this->assertNull($plan[4]['id']);
        $this->assertSame(2, $plan[4]['type']);
        $this->assertSame('استراحة الطلاب بعد الحصة الرابعة', $plan[4]['presentation']?->name);
        // Lessons keep their ids (placed lessons stay on them).
        $this->assertSame([11, 12, 13, 14, null, 15, 16], array_column($plan, 'id'));
    }

    #[Test]
    public function a_break_before_the_first_lesson_starts_the_day(): void
    {
        $plan = (new BellScheduleCalculator)->insertBreak($this->day(), null, 10, new PeriodPresentation);

        $this->assertIsArray($plan);
        $this->assertSame('08:00', $plan[0]['start']);
        $this->assertSame('08:10', $plan[1]['start']);
    }

    #[Test]
    public function several_breaks_and_consecutive_breaks_are_allowed(): void
    {
        $calculator = new BellScheduleCalculator;
        $once = $calculator->insertBreak($this->day(), 13, 15, new PeriodPresentation);
        $this->assertIsArray($once);
        $twice = $calculator->insertBreak($this->snapshots($once), 13, 5, new PeriodPresentation);

        $this->assertIsArray($twice);
        $this->assertSame([1, 1, 1, 2, 2, 1, 1, 1], array_column($twice, 'type'));
        $this->assertSame(['09:30', '10:15', '10:20', '10:35'], array_slice(array_column($twice, 'start'), 2, 4));
    }

    #[Test]
    public function resizing_a_break_shifts_by_the_difference_and_keeps_gaps(): void
    {
        $day = $this->day();
        $day[] = new PeriodSnapshot(30, 1, 7, '13:00:00', '13:10:00', 2);
        $plan = (new BellScheduleCalculator)->resize($day, 30, 25);

        $this->assertIsArray($plan);
        $this->assertSame('13:25', end($plan)['end']);
    }

    #[Test]
    public function moving_a_break_closes_the_day_behind_it_and_opens_where_it_lands(): void
    {
        $calculator = new BellScheduleCalculator;
        $withBreak = $calculator->insertBreak($this->day(), 12, 15, new PeriodPresentation);
        $this->assertIsArray($withBreak);
        $day = $this->snapshots($withBreak, breakId: 40);

        $moved = $calculator->moveBreak($day, 40, 15);

        $this->assertIsArray($moved);
        $this->assertSame([11, 12, 13, 14, 15, 40, 16], array_column($moved, 'id'));
        $this->assertSame(['08:00', '08:45', '09:30', '10:15', '11:00', '11:45', '12:00'], array_column($moved, 'start'));
    }

    #[Test]
    public function removing_a_break_moves_the_rest_earlier_and_reports_it_retired(): void
    {
        $calculator = new BellScheduleCalculator;
        $withBreak = $calculator->insertBreak($this->day(), 13, 15, new PeriodPresentation);
        $this->assertIsArray($withBreak);

        $removed = $calculator->removeBreak($this->snapshots($withBreak, breakId: 40), 40);

        $this->assertIsArray($removed);
        $this->assertSame(40, $removed['retired']);
        $this->assertSame(['08:00', '08:45', '09:30', '10:15', '11:00', '11:45'], array_column($removed['day'], 'start'));
    }

    #[Test]
    public function only_breaks_can_be_moved_or_removed(): void
    {
        $calculator = new BellScheduleCalculator;

        $this->assertSame('timetable.break_not_found', $calculator->removeBreak($this->day(), 12));
        $this->assertSame('timetable.break_not_found', $calculator->moveBreak($this->day(), 12, 14));
    }

    #[Test]
    public function retime_a_single_period_with_or_without_cascade(): void
    {
        $calculator = new BellScheduleCalculator;

        $cascade = $calculator->retime($this->day(), 12, '08:45', '09:40', true);
        $this->assertIsArray($cascade);
        $this->assertSame('09:40', $cascade[2]['start']);

        // Without cascade the next period would overlap: refused.
        $this->assertSame('timetable.period_overlap', $calculator->retime($this->day(), 12, '08:45', '09:40', false));
        // Shortening without cascade leaves a gap (variable timing).
        $single = $calculator->retime($this->day(), 12, '08:45', '09:20', false);
        $this->assertIsArray($single);
        $this->assertSame('09:30', $single[2]['start']);
    }

    #[Test]
    public function a_fixed_pattern_gives_every_lesson_the_same_length_and_keeps_breaks(): void
    {
        $day = [
            new PeriodSnapshot(1, 1, 1, '08:00:00', '08:40:00', 1),
            new PeriodSnapshot(2, 1, 2, '08:45:00', '09:00:00', 2),
            new PeriodSnapshot(3, 1, 3, '09:10:00', '09:55:00', 1),
        ];
        $plan = (new BellScheduleCalculator)->fixedPattern($day, '07:30', 50);

        $this->assertIsArray($plan);
        $this->assertSame([['07:30', '08:20'], ['08:20', '08:35'], ['08:35', '09:25']], array_map(static fn (array $p): array => [$p['start'], $p['end']], $plan));
    }

    #[Test]
    public function the_day_never_runs_past_midnight_and_lengths_are_bounded(): void
    {
        $calculator = new BellScheduleCalculator;

        $this->assertSame('timetable.day_past_midnight', $calculator->fixedPattern($this->day(), '22:00', 60));
        $this->assertSame('timetable.break_minutes_invalid', $calculator->insertBreak($this->day(), 11, 0, new PeriodPresentation));
        $this->assertSame('timetable.lesson_minutes_invalid', $calculator->resize($this->day(), 11, 2));
        $this->assertSame('timetable.period_not_in_school', $calculator->insertBreak($this->day(), 999, 10, new PeriodPresentation));
    }

    #[Test]
    public function presentation_targets_are_a_bitmask(): void
    {
        $p = PeriodPresentation::of('  استراحة   كبرى ', ' ك ', 30, PeriodPresentation::GENERAL | PeriodPresentation::SECTIONS, PeriodPresentation::EVERYWHERE);

        $this->assertSame('استراحة كبرى', $p->name);
        $this->assertSame('ك', $p->abbreviation);
        $this->assertTrue($p->shows(PeriodPresentation::SECTIONS));
        $this->assertFalse($p->shows(PeriodPresentation::TEACHERS));
        $this->assertTrue($p->prints(PeriodPresentation::STUDENTS));
        $this->assertNull($p->rejection());
        $this->assertSame('timetable.period_targets_invalid', (new PeriodPresentation(showIn: 64))->rejection());
        $this->assertSame('appearance.color_invalid', (new PeriodPresentation(colorHue: 400))->rejection());
    }

    /** Six 45-minute lessons from 08:00, back to back (ids 11–16). */
    private function day(): array
    {
        $day = [];
        for ($n = 1; $n <= 6; $n++) {
            $start = 8 * 60 + ($n - 1) * 45;
            $day[] = new PeriodSnapshot(10 + $n, 1, $n, sprintf('%02d:%02d:00', intdiv($start, 60), $start % 60), sprintf('%02d:%02d:00', intdiv($start + 45, 60), ($start + 45) % 60), 1);
        }

        return $day;
    }

    /** A computed plan back as snapshots (new rows get `breakId`, then 41, 42 …). */
    private function snapshots(array $plan, int $breakId = 40): array
    {
        $next = $breakId;

        return array_map(static function (array $p) use (&$next): PeriodSnapshot {
            return new PeriodSnapshot($p['id'] ?? $next++, 1, $p['number'], $p['start'].':00', $p['end'].':00', $p['type']);
        }, $plan);
    }
}
