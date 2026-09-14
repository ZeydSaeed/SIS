<?php

namespace App\Application\Vocational\DTOs;

final readonly class SpecializationSubjectDTO
{
    public function __construct(
        public int $id,
        public int $specializationId,
        public int $schoolId,
        public int $subjectId,
        public bool $isRequired,
        public ?int $creditHours,
        public int $status,
    ) {}
}
