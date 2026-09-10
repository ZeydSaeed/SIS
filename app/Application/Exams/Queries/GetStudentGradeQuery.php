<?php

namespace App\Application\Exams\Queries;

final readonly class GetStudentGradeQuery
{
    public function __construct(
        public int $gradeId,
        public int $academicYearId,
        public int $schoolId,
    ) {}
}
