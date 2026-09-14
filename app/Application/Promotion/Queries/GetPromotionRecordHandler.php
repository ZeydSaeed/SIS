<?php

namespace App\Application\Promotion\Queries;

use App\Application\Promotion\DTOs\PromotionRecordDTO;
use App\Domain\Promotion\Repositories\PromotionRepositoryInterface;

final class GetPromotionRecordHandler
{
    public function __construct(
        private readonly PromotionRepositoryInterface $promotions,
    ) {}

    public function handle(GetPromotionRecordQuery $query): ?PromotionRecordDTO
    {
        $row = $this->promotions->findRecord($query->schoolId, $query->recordId);
        if ($row === null) {
            return null;
        }

        return new PromotionRecordDTO(
            id: $row->id,
            schoolId: $row->schoolId,
            enrollmentId: $row->enrollmentId,
            academicYearId: $row->academicYearId,
            fromGradeLevelId: $row->fromGradeLevelId,
            toGradeLevelId: $row->toGradeLevelId,
            promotionStatus: $row->promotionStatus,
            gpaAtPromotion: $row->gpaAtPromotion,
            decidedBy: $row->decidedBy,
            decidedAt: $row->decidedAt,
            notes: $row->notes,
            createdAt: $row->createdAt,
        );
    }
}
