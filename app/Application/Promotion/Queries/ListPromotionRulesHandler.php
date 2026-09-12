<?php

namespace App\Application\Promotion\Queries;

use App\Application\Promotion\DTOs\PromotionRuleDTO;
use App\Domain\Promotion\Repositories\PromotionRepositoryInterface;

final class ListPromotionRulesHandler
{
    public function __construct(
        private readonly PromotionRepositoryInterface $promotion,
    ) {}

    /**
     * @return list<PromotionRuleDTO>
     */
    public function handle(ListPromotionRulesQuery $query): array
    {
        $items = [];
        foreach ($this->promotion->listRules($query->schoolId, $query->activeOnly) as $row) {
            $items[] = new PromotionRuleDTO(
                $row->id,
                $row->schoolId,
                $row->fromGradeLevelId,
                $row->toGradeLevelId,
                $row->minGpa,
                $row->minPassSubjects,
                $row->maxFailedSubjects,
                $row->isActive,
                $row->createdAt,
            );
        }

        return $items;
    }
}
