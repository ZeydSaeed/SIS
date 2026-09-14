<?php

namespace App\Application\Exams\Queries;

final readonly class GetExamQuery
{
    public function __construct(
        public int $schoolId,
        public int $examId,
    ) {}
}
