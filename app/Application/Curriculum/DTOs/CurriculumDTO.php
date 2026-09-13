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
    ) {}
}
