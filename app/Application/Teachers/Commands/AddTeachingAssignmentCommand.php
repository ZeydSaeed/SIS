<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;

/** The teacher teaches the subject in a branch (+ optional department, class, section). */
final readonly class AddTeachingAssignmentCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $teacherId,
        public int $subjectId,
        public int $branchId,
        public ?int $departmentId,
        public ?int $classId,
        public ?int $sectionId,
        public ?string $idempotencyKey,
    ) {}
}
