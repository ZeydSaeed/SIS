<?php

namespace App\Domain\Attendance\Data;

/**
 * Enrollment fields required for ATT-D4 attendance-date validity.
 */
final readonly class EnrollmentAttendanceContext
{
    public function __construct(
        public int $id,
        public int $studentId,
        public int $schoolId,
        public int $academicYearId,
        public int $sectionId,
        public string $effectiveFrom,
        public ?string $effectiveTo,
        public int $status,
    ) {}
}
