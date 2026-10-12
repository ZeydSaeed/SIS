<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;

/** Evaluate the alert rules for a school on a date (normally run by the scheduled job, never inside a request). */
final readonly class DetectAttendanceAlertsCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $date,
        public string $idempotencyKey,
    ) {}
}
