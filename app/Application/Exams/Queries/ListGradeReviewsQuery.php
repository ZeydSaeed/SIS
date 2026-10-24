<?php

namespace App\Application\Exams\Queries;

final readonly class ListGradeReviewsQuery
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public ?string $status,
    ) {}
}
