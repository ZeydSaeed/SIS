<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;

final readonly class CorrectAttendanceRecordCommand implements Command
{
    public function __construct(
        public int $sessionId,
        public int $studentId,
        public int $academicYearId,
        public int $schoolId,
        public int $newStatus,
        public ?string $newNotes,
        public string $reason,
        public ?int $recordedBy,
        public string $idempotencyKey,
    ) {}
}
