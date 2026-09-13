<?php

namespace App\Application\Curriculum\DTOs;

final readonly class SubjectDTO
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public ?string $nameEn,
        public int $subjectType,
        public ?int $creditHours,
        public int $maxGrade,
        public int $passGrade,
        public int $status,
    ) {}
}
