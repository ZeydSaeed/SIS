<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;

/**
 * A lesson that is not in the timetable (make-up, merged class, field activity) — opened only by an authorised
 * reviewer, always with a reason that stays in the sheet history.
 */
final readonly class OpenExceptionalAttendanceCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $date,
        public int $sectionId,
        public int $subjectId,
        public int $teacherId,
        public int $periodId,
        public string $reason,
        public int $actorUserId,
        public string $idempotencyKey,
    ) {}
}
