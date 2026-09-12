<?php

namespace App\Application\Promotion\Commands;

use App\Application\Contracts\Command;

final readonly class CreatePromotionRuleCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $fromGradeLevelId,
        public int $toGradeLevelId,
        public ?string $minGpa,
        public ?int $minPassSubjects,
        public ?int $maxFailedSubjects,
        public bool $isActive,
        public ?string $idempotencyKey,
    ) {}
}
