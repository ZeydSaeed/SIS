<?php

namespace Tests\Unit\Domain;

use App\Domain\Teachers\ValueObjects\TeacherWorkloadLimits;
use App\Domain\Timetable\Data\PeriodSnapshot;
use App\Domain\Timetable\Data\TimetableBoard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TeacherWorkloadLimitsTest extends TestCase
{
    /** @return array<string, array{0: ?int, 1: ?int, 2: ?int, 3: ?string}> */
    public static function cases(): array
    {
        return [
            'no limits' => [null, null, null, null],
            'coherent' => [10, 20, 5, null],
            'min equals max' => [20, 20, 6, null],
            'weekly max zero' => [null, 0, null, 'teachers.workload_weekly_max_invalid'],
            'weekly max above 60' => [null, 61, null, 'teachers.workload_weekly_max_invalid'],
            'weekly min negative' => [-1, 20, null, 'teachers.workload_weekly_min_invalid'],
            'daily zero' => [null, null, 0, 'teachers.workload_daily_max_invalid'],
            'daily above 12' => [null, null, 13, 'teachers.workload_daily_max_invalid'],
            'min above max' => [15, 10, null, 'teachers.workload_min_above_max'],
            'daily above weekly' => [null, 4, 6, 'teachers.workload_daily_above_weekly'],
            'daily alone is fine' => [null, null, 12, null],
        ];
    }

    #[Test]
    #[DataProvider('cases')]
    public function validates_the_limits(?int $min, ?int $max, ?int $daily, ?string $error): void
    {
        $this->assertSame($error, TeacherWorkloadLimits::error($min, $max, $daily));
    }

    #[Test]
    public function board_capacity_follows_the_personal_limits(): void
    {
        $periods = [];
        for ($n = 1; $n <= 7; $n++) {
            $periods[] = new PeriodSnapshot($n, 1, $n, sprintf('%02d:00:00', 7 + $n), sprintf('%02d:45:00', 7 + $n), 1);
        }
        $board = new TimetableBoard($periods, [], [], [], [], [], [], null, [], [], [], [], [], [], [], [
            1 => ['weekly_min' => null, 'weekly_max' => 12, 'daily_max' => null],
            2 => ['weekly_min' => 5, 'weekly_max' => null, 'daily_max' => 3],
        ]);

        // School default: 5 days × 6 lessons a day.
        $this->assertSame(30, $board->weeklyCapacity(99));
        $this->assertSame(6, $board->dailyLimit(99));
        // Personal weekly maximum caps the week.
        $this->assertSame(12, $board->weeklyCapacity(1));
        // Personal daily limit shrinks the week (5 × 3).
        $this->assertSame(3, $board->dailyLimit(2));
        $this->assertSame(15, $board->weeklyCapacity(2));
        // The copies of a board keep the limits.
        $this->assertSame(12, $board->withSchedules([])->weeklyCapacity(1));
    }
}
