<?php

namespace App\Application\Promotion\DTOs;

final readonly class PromotionRuleDTO
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
