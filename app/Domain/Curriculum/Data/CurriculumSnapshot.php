<?php

namespace App\Domain\Curriculum\Data;

final readonly class CurriculumSnapshot
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $academicYearId,
        public int $gradeLevelId,
        public ?int $specializationId,
        public string $name,
        public int $status,
    ) {}
}
