<?php

namespace App\Application\Organization\Queries;

use App\Application\Contracts\Query;

final readonly class GetBranchStructureQuery implements Query
{
    public function __construct(
        public int $schoolId,
    ) {}
}
