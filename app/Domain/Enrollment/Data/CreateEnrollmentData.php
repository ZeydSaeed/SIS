<?php

namespace App\Domain\Enrollment\Data;

final readonly class CreateEnrollmentData
{
    public function __construct(
        public int $studentId,
        public int $academicYearId,
        public int $schoolId,
        public int $classId,
        public int $sectionId,
        public string $enrollmentNumber,
        public string $effectiveFrom,
        public ?int $specializationId = null,
        public ?int $enrolledBy = null,
    ) {}
}
