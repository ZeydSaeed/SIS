<?php

namespace App\Domain\Exams\Data;

/**
 * One active teaching assignment (teachers.teaching_assignments): a subject in a branch, optionally narrowed to a
 * department, class and section. A null narrowing column covers every value inside the wider scope.
 */
final readonly class TeachingScope
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $subjectId,
        public int $branchId,
        public ?int $departmentId,
        public ?int $classId,
        public ?int $sectionId,
    ) {}
}
