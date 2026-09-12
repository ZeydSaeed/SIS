<?php

namespace App\Application\Promotion\DTOs;

final readonly class PromotionRecordDTO
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
