<?php

namespace App\Application\Exams\Queries;

final readonly class ListGradesForExamSessionQuery
{
    public function __construct(
        public int $examSessionId,
        public int $academicYearId,
        public int $schoolId,
    ) {}
}
