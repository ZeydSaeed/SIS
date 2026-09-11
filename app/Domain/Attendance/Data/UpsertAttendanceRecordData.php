<?php

namespace App\Domain\Attendance\Data;

final readonly class UpsertAttendanceRecordData
{
    public function __construct(
        public int $sessionId,
        public int $studentId,
        public int $enrollmentId,
        public int $academicYearId,
        public int $schoolId,
        public string $attendanceDate,
        public int $status,
        public ?string $notes,
        public ?int $recordedBy,
    ) {}
}
