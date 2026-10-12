<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;

/**
 * Administrative entry or correction of a teacher's work attendance for one day. Correcting an existing row
 * requires the version the user saw and a reason.
 */
final readonly class RecordStaffAttendanceCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $teacherId,
        public string $workDate,
        public int $status,
        /** School-local HH:MM. */
        public ?string $checkInAt,
        public ?string $checkOutAt,
        public ?int $minutesLate,
        public ?string $notes,
        public ?string $reason,
        public ?int $expectedVersion,
        public int $actorUserId,
        public string $idempotencyKey,
    ) {}
}
