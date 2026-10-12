<?php

namespace App\Domain\Attendance\Data;

final readonly class ExcuseSnapshot
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $academicYearId,
        public int $studentId,
        public int $enrollmentId,
        public string $dateFrom,
        public string $dateTo,
        public ?int $sessionId,
        public int $excuseType,
        public int $status,
        public int $version,
        public ?int $submittedBy,
    ) {}
}
