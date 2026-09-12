<?php

namespace App\Domain\Promotion\Data;

final readonly class PromotionRuleSnapshot
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $fromGradeLevelId,
        public int $toGradeLevelId,
        public ?string $minGpa,
        public ?int $minPassSubjects,
        public ?int $maxFailedSubjects,
        public bool $isActive,
        public string $createdAt,
    ) {}
}
