<?php

namespace App\Application\Promotion\Commands;

use App\Application\Contracts\Command;

final readonly class ReactivatePromotionRuleCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $ruleId,
        public ?string $idempotencyKey,
    ) {}
}
