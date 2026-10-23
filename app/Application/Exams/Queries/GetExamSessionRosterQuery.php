<?php

namespace App\Application\Exams\Queries;

final readonly class GetExamSessionRosterQuery
{
    public function __construct(
        public int $schoolId,
        public int $examId,
        public int $examSessionId,
    ) {}
}
