<?php

namespace App\Domain\Promotion\Data;

final readonly class PromotionRecordSnapshot
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $enrollmentId,
        public int $academicYearId,
        public int $fromGradeLevelId,
        public int $toGradeLevelId,
        public int $promotionStatus,
        public ?string $gpaAtPromotion,
        public ?int $decidedBy,
        public string $decidedAt,
        public ?string $notes,
        public string $createdAt,
    ) {}
}
