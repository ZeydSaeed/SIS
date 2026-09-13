<?php

namespace App\Application\Hr\Queries;

final readonly class ListEmployeesQuery
{
    public function __construct(
        public int $schoolId,
        public ?int $academicYearId = null,
        public ?int $status = null,
    ) {}
}
