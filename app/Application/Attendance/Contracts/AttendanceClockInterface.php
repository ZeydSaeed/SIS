<?php

namespace App\Application\Attendance\Contracts;

/** Server time for attendance windows (never the client's clock). */
interface AttendanceClockInterface
{
    public function now(): \DateTimeImmutable;
}
