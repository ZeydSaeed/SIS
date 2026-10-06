<?php

namespace App\Domain\Teachers\Data;

/** A teacher teaches a subject in a branch / department / class / section (school + year). */
final readonly class TeachingAssignmentData
{
    public function __construct(
        public int $teacherId,
        public int $schoolId,
        public int $academicYearId,
        public int $subjectId,
        public int $branchId,
        public ?int $departmentId,
        public ?int $classId,
        public ?int $sectionId,
        public string $effectiveFrom,
        public string $at,
    ) {}
}
