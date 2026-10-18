<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;

final readonly class ListLeaveTypesQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public bool $withHistory = false,
    ) {}
}
