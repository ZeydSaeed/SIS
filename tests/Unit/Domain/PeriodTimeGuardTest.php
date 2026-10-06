<?php

namespace Tests\Unit\Domain;

use App\Domain\Timetable\Data\PeriodSnapshot;
use App\Domain\Timetable\Data\PersistPeriodData;
use App\Domain\Timetable\Services\PeriodTimeGuard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PeriodTimeGuardTest extends TestCase
{
    #[Test]
    public function a_period_fits_between_the_others(): void
    {
        $this->assertNull((new PeriodTimeGuard)->error($this->data(2, '08:45', '09:30'), $this->day()));
    }

    #[Test]
    public function start_must_precede_end_and_times_must_be_valid(): void
    {
        $guard = new PeriodTimeGuard;

        $this->assertSame('timetable.period_time_invalid', $guard->error($this->data(2, '09:30', '09:30'), []));
        $this->assertSame('timetable.period_time_invalid', $guard->error($this->data(2, '10:00', '09:00'), []));
        $this->assertSame('timetable.period_time_invalid', $guard->error($this->data(2, '25:00', '26:00'), []));
    }

    #[Test]
    public function numbers_are_unique_and_minutes_are_never_shared(): void
    {
        $guard = new PeriodTimeGuard;

        $this->assertSame('timetable.period_number_taken', $guard->error($this->data(1, '11:00', '11:45'), $this->day()));
        $this->assertSame('timetable.period_overlap', $guard->error($this->data(2, '08:30', '09:00'), $this->day()));
        // Touching edges is not an overlap.
        $this->assertNull($guard->error($this->data(2, '08:45', '10:00'), $this->day()));
    }

    #[Test]
    public function the_edited_period_is_not_compared_with_itself(): void
    {
        $this->assertNull((new PeriodTimeGuard)->error($this->data(1, '08:00', '08:50'), $this->day(), 10));
    }

    #[Test]
    public function number_and_type_ranges_are_checked(): void
    {
        $guard = new PeriodTimeGuard;

        $this->assertSame('timetable.period_number_invalid', $guard->error($this->data(0, '08:00', '08:45'), []));
        $this->assertSame('timetable.period_type_invalid', $guard->error(new PersistPeriodData(1, 2, '08:00', '08:45', 9), []));
    }

    private function data(int $number, string $start, string $end): PersistPeriodData
    {
        return new PersistPeriodData(schoolId: 1, periodNumber: $number, startTime: $start, endTime: $end, periodType: 1);
    }

    /** @return list<PeriodSnapshot> */
    private function day(): array
    {
        return [
            new PeriodSnapshot(id: 10, schoolId: 1, periodNumber: 1, startTime: '08:00:00', endTime: '08:45:00', periodType: 1),
            new PeriodSnapshot(id: 11, schoolId: 1, periodNumber: 3, startTime: '10:00:00', endTime: '10:45:00', periodType: 1),
        ];
    }
}
