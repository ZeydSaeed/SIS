<?php

namespace App\Application\Organization\Queries;

final readonly class ListBranchesQuery
{
    public function __construct(
        public int $schoolId,
    ) {}
}
