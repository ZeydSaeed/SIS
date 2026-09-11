<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;

final readonly class MarkSectionAttendanceCommand implements Command
{
    /**
     * @param  list<array{studentId: int, enrollmentId: int, status: int, notes?: string|null}>  $records
     */
    public function __construct(
        public int $sessionId,
        public int $schoolId,
        public int $academicYearId,
        public array $records,
        public ?int $recordedBy,
        public string $idempotencyKey,
    ) {}
}
