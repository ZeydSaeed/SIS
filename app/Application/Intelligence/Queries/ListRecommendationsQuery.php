<?php

namespace App\Application\Intelligence\Queries;

use App\Application\Contracts\Query;

final readonly class ListRecommendationsQuery implements Query
{
    public function __construct(
        public string $status = 'pending',
        public int $perPage = 20,
    ) {}
}
