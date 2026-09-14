<?php

namespace App\Application\Organization\Queries;

final readonly class GetDepartmentQuery
{
    public function __construct(
        public int $schoolId,
        public int $departmentId,
    ) {}
}
