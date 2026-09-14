<?php

namespace App\Application\Exams\Queries;

final readonly class ListExamSessionsQuery
{
    public function __construct(
        public int $schoolId,
        public int $examId,
        public ?int $status = null,
    ) {}
}
