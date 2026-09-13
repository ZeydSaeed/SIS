<?php

namespace App\Application\Promotion\Queries;

final readonly class GetPromotionRuleQuery
{
    public function __construct(
        public int $schoolId,
        public int $ruleId,
    ) {}
}
