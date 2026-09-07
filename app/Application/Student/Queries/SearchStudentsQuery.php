<?php

namespace App\Application\Student\Queries;

use App\Application\Contracts\Query;

final readonly class SearchStudentsQuery implements Query
{
    public function __construct(
        public string $term,
        public int $schoolId = 0,
        public int $page = 1,
        public int $perPage = 25,
    ) {}
}
