<?php

namespace App\Application\Student\Queries;

use App\Application\Contracts\Query;

final readonly class ListStudentsQuery implements Query
{
    public function __construct(
        public ?int $status = null,
        public int $schoolId = 0,
        public int $page = 1,
        public int $perPage = 25,
    ) {}
}
