<?php

namespace App\Application\Vocational\Queries;

use App\Application\Contracts\Query;

final readonly class ListSpecializationsQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public ?int $status = null,
        public int $page = 1,
        public int $perPage = 50,
    ) {}
}
