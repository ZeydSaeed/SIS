<?php

namespace App\Infrastructure\Attendance;

use App\Application\Attendance\Contracts\AttendanceClockInterface;
use Illuminate\Support\Carbon;

/** Server clock (Carbon, so tests can freeze it with Carbon::setTestNow). */
final class SystemAttendanceClock implements AttendanceClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return Carbon::now()->toDateTimeImmutable();
    }
}
