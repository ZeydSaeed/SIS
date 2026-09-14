<?php

namespace App\Application\Exams\Queries;

final readonly class GetExamSessionQuery
{
    public function __construct(
        public int $schoolId,
        public int $examSessionId,
    ) {}
}
