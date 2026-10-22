<?php

namespace App\Domain\Exams\Data;

/**
 * Where an exam seat sits academically — the exam's school/year/subject and the student's placement
 * (enrollment branch, department = الاختصاص, class, section). Used to decide who may grade the seat.
 */
final readonly class GradeSeatPlacement
{
    public function __construct(
        public int $examEnrollmentId,
        public int $schoolId,
        public int $academicYearId,
        public int $subjectId,
        public ?int $branchId,
        public ?int $departmentId,
        public int $classId,
        public int $sectionId,
        public int $examSessionId = 0,
    ) {}
}
