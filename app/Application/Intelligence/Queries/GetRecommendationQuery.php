<?php

namespace App\Application\Intelligence\Queries;

use App\Application\Contracts\Query;

final readonly class GetRecommendationQuery implements Query
{
    public function __construct(
        public int $recommendationId,
    ) {}
}
