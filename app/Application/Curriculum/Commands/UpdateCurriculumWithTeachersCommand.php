<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;

/**
 * «تعديل منهج» in one step: the curriculum's year, place, name and «عدد المواد», its subjects (added, returned,
 * removed) and the teachers of each subject (whole subject or one part, one section or all, main / assistant).
 */
final readonly class UpdateCurriculumWithTeachersCommand implements Command
{
    /**
     * @param  list<int>  $subjectIds  the curriculum's subjects, in order
     * @param  list<array{subject_id: int, subject_type: ?int, teacher_id: int, role: int, section_id: ?int}>  $teachers
     */
    public function __construct(
        public int $schoolId,
        public int $curriculumId,
        public int $academicYearId,
        public int $gradeLevelId,
        public int $branchId,
        public int $departmentId,
        public int $classId,
        public string $name,
        public ?int $plannedSubjectCount,
        public array $subjectIds,
        public array $teachers,
        public ?string $idempotencyKey,
    ) {}
}
