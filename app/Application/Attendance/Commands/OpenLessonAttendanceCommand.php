<?php

namespace App\Application\Attendance\Commands;

use App\Application\Attendance\Support\SheetActor;
use App\Application\Contracts\Command;

/** Open (or resume) the attendance sheet of a timetable lesson on a date. */
final readonly class OpenLessonAttendanceCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $scheduleId,
        public string $date,
        public SheetActor $actor,
        public string $idempotencyKey,
    ) {}
}
