<?php

namespace Tests\Unit\Domain;

use App\Domain\Timetable\Services\SchoolDayPlanner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchoolDayPlannerTest extends TestCase
{
    #[Test]
    public function seven_lessons_get_breaks_of_five_ten_and_fifteen_minutes(): void
    {
        $day = (new SchoolDayPlanner)->plan(7, '08:00', 45);

        $this->assertNotNull($day);
        $this->assertCount(13, $day);
        $this->assertSame(range(1, 13), array_column($day, 'number'));
        $this->assertSame([1, 2, 1, 2, 1, 2, 1, 2, 1, 2, 1, 2, 1], array_column($day, 'type'));

        $breaks = array_values(array_filter($day, static fn (array $p): bool => $p['type'] === 2));
        $minutes = array_map(static fn (array $p): int => self::minutes($p['end']) - self::minutes($p['start']), $breaks);
        $this->assertSame([5, 5, 15, 5, 10, 5], $minutes);

        $this->assertSame('08:00', $day[0]['start']);
        $this->assertSame('14:00', $day[12]['end']);
        // Back to back: each period starts when the previous one ends.
        for ($i = 1; $i < 13; $i++) {
            $this->assertSame($day[$i - 1]['end'], $day[$i]['start']);
        }
    }

    #[Test]
    public function bad_input_or_a_day_past_midnight_gives_no_plan(): void
    {
        $planner = new SchoolDayPlanner;

        $this->assertNull($planner->plan(7, '8 am', 45));
        $this->assertNull($planner->plan(0, '08:00', 45));
        $this->assertNull($planner->plan(12, '22:00', 60));
        $this->assertCount(1, $planner->plan(1, '08:00', 40) ?? []);
    }

    private static function minutes(string $time): int
    {
        [$h, $m] = array_map(intval(...), explode(':', $time));

        return $h * 60 + $m;
    }
}
