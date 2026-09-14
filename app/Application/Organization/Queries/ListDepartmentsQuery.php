<?php

namespace App\Application\Organization\Queries;

final readonly class ListDepartmentsQuery
{
    public function __construct(
        public int $schoolId,
        public ?int $branchId = null,
    ) {}
}
