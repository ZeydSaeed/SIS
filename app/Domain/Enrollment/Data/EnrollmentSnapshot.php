<?php

namespace App\Domain\Enrollment\Data;

use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;

final readonly class EnrollmentSnapshot
{
    public function __construct(
        public int $id,
        public int $studentId,
        public int $schoolId,
        public int $academicYearId,
        public int $classId,
        public int $sectionId,
        public ?int $specializationId,
        public string $enrollmentNumber,
        public int $status,
        public string $effectiveFrom,
        public ?string $effectiveTo,
    ) {}

    public function isActive(): bool
    {
        return EnrollmentStatus::isActive($this->status, $this->effectiveTo);
    }
}
