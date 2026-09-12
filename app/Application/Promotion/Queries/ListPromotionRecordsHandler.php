<?php

namespace App\Application\Promotion\Queries;

use App\Application\Promotion\DTOs\PromotionRecordDTO;
use App\Domain\Promotion\Repositories\PromotionRepositoryInterface;

final class ListPromotionRecordsHandler
{
    public function __construct(
        private readonly PromotionRepositoryInterface $promotion,
    ) {}

    /**
     * @return list<PromotionRecordDTO>
     */
    public function handle(ListPromotionRecordsQuery $query): array
    {
        $items = [];
        foreach ($this->promotion->listRecords($query->schoolId, $query->academicYearId) as $row) {
            $items[] = new PromotionRecordDTO(
                $row->id,
                $row->schoolId,
                $row->enrollmentId,
                $row->academicYearId,
                $row->fromGradeLevelId,
                $row->toGradeLevelId,
                $row->promotionStatus,
                $row->gpaAtPromotion,
                $row->decidedBy,
                $row->decidedAt,
                $row->notes,
                $row->createdAt,
            );
        }

        return $items;
    }
}
