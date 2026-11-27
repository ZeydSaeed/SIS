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
        /** TeachingRole — 1 main (default), 2 assistant. */
        public int $role = 1,
        /** The curriculum subject line it was assigned on («المناهج»); null when set on «المعلمون». */
        public ?int $curriculumSubjectId = null,
        /** The part of the subject taught (one of its types: نظري، عملي …); null = the whole subject. */
        public ?int $subjectType = null,
    ) {}
}
