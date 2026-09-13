<?php

namespace App\Application\Promotion\Queries;

use App\Application\Promotion\DTOs\PromotionRuleDTO;
use App\Domain\Promotion\Repositories\PromotionRepositoryInterface;

final class GetPromotionRuleHandler
{
    public function __construct(
        private readonly PromotionRepositoryInterface $promotions,
    ) {}

    public function handle(GetPromotionRuleQuery $query): ?PromotionRuleDTO
    {
        $row = $this->promotions->findRule($query->schoolId, $query->ruleId);
        if ($row === null) {
            return null;
        }

        return new PromotionRuleDTO(
            id: $row->id,
            schoolId: $row->schoolId,
            fromGradeLevelId: $row->fromGradeLevelId,
            toGradeLevelId: $row->toGradeLevelId,
            minGpa: $row->minGpa,
            minPassSubjects: $row->minPassSubjects,
            maxFailedSubjects: $row->maxFailedSubjects,
            isActive: $row->isActive,
            createdAt: $row->createdAt,
        );
    }
}
