<?php

namespace App\Application\Promotion\Queries;

final readonly class ListPromotionRulesQuery
{
    public function __construct(
        public int $schoolId,
        public ?bool $activeOnly = null,
    ) {}
}
