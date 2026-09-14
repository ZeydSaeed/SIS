<?php

namespace App\Application\Enrollment\Queries;

final readonly class ListClassesQuery
{
    public function __construct(
        public int $schoolId,
        public ?int $academicYearId = null,
    ) {}
}
