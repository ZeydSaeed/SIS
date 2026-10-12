<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;

/** Issue a new short-lived check-in code for the school (replaces any still-valid code). */
final readonly class IssueStaffCheckinCodeCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $actorUserId,
        public string $idempotencyKey,
    ) {}
}
