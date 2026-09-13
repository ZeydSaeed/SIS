<?php

namespace App\Domain\Curriculum\Data;

final readonly class SubjectSnapshot
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
