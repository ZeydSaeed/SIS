<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;
use App\Domain\Attendance\ValueObjects\AttendancePolicy;

/** Saves a new policy version for the school year (the previous version is kept for the sheets it governed). */
final readonly class SaveAttendancePolicyCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public AttendancePolicy $values,
        public int $actorUserId,
        public string $idempotencyKey,
    ) {}
}
