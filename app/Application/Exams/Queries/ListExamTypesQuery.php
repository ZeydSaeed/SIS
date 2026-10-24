<?php

namespace App\Application\Exams\Queries;

final readonly class ListExamTypesQuery
{
    public function __construct(
        public int $schoolId,
        public ?int $academicYearId = null,
    ) {}
}
