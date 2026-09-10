<?php

namespace App\Application\Exams\Queries;

final readonly class GetCurrentGradeForExamEnrollmentQuery
{
    public function __construct(
        public int $examEnrollmentId,
        public int $academicYearId,
        public int $schoolId,
    ) {}
}
