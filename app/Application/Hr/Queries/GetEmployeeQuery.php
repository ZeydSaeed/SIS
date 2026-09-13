<?php

namespace App\Application\Hr\Queries;

final readonly class GetEmployeeQuery
{
    public function __construct(
        public int $schoolId,
        public int $employeeId,
    ) {}
}
