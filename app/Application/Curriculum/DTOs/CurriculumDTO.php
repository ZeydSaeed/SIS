<?php

namespace App\Application\Curriculum\DTOs;

final readonly class CurriculumDTO
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $academicYearId,
        public int $gradeLevelId,
        public ?int $specializationId,
        public string $name,
        public int $status,
        public ?int $departmentId = null,
        /** الفرع / الصف / الشعبة the curriculum belongs to (null on curricula made before 2026-11-27). */
        public ?int $branchId = null,
        public ?int $classId = null,
        public ?int $sectionId = null,
        /** «عدد المواد» it should have (null = not stated). */
        public ?int $plannedSubjectCount = null,
    ) {}
}
