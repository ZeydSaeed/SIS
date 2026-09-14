<?php

namespace App\Application\Exams\Queries;

final readonly class GetExamEnrollmentQuery
{
    public function __construct(
        public int $schoolId,
        public int $examEnrollmentId,
    ) {}
}
