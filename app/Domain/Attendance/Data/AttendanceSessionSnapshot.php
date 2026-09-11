<?php

namespace App\Domain\Attendance\Data;

final readonly class AttendanceSessionSnapshot
{
    public function __construct(
        public int $id,
        public int $sectionId,
        public int $subjectId,
        public int $academicYearId,
        public string $sessionDate,
        public ?int $periodId,
        public int $teacherId,
        public int $status,
        public ?int $resolvedSchoolId = null,
    ) {}

    public function statusValue(): int
    {
        return $this->status;
    }
}
