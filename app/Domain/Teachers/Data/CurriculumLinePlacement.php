<?php

namespace App\Domain\Teachers\Data;

/** A subject line of a curriculum, with the place its teachers teach in (the curriculum's branch … section). */
final readonly class CurriculumLinePlacement
{
    public function __construct(
        public int $lineId,
        public int $curriculumId,
        public int $subjectId,
        public int $schoolId,
        public int $academicYearId,
        public ?int $branchId,
        public ?int $departmentId,
        public ?int $classId,
        public ?int $sectionId,
        public bool $lineActive,
        public bool $curriculumActive,
    ) {}

    /** Teachers are assigned only once the curriculum names its branch, class and section. */
    public function isPlaced(): bool
    {
        return $this->branchId !== null && $this->classId !== null && $this->sectionId !== null;
    }
}
