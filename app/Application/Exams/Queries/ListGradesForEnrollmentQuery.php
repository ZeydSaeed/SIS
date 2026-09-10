<?php

namespace App\Application\Exams\Queries;

final readonly class ListGradesForEnrollmentQuery
{
    public function __construct(
        public int $enrollmentId,
        public int $academicYearId,
        public int $schoolId,
    ) {}
}
