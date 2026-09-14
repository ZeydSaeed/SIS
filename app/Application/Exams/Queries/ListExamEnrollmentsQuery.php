<?php

namespace App\Application\Exams\Queries;

final readonly class ListExamEnrollmentsQuery
{
    public function __construct(
        public int $schoolId,
        public int $examSessionId,
        public ?int $status = null,
    ) {}
}
