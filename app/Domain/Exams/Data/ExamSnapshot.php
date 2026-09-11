<?php

namespace App\Domain\Exams\Data;

final readonly class ExamSnapshot
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $examTypeId,
        public string $name,
        public string $startDate,
        public string $endDate,
        public int $status,
    ) {}
}
