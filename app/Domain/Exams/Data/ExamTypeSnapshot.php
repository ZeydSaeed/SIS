<?php

namespace App\Domain\Exams\Data;

final readonly class ExamTypeSnapshot
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public string $code,
        public string $name,
        public int $weightPercentage,
        public bool $inUse,
    ) {}
}
