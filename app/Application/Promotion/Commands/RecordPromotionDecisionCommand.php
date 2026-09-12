<?php

namespace App\Application\Promotion\Commands;

use App\Application\Contracts\Command;

final readonly class RecordPromotionDecisionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $academicYearId,
        public int $toGradeLevelId,
        public int $promotionStatus,
        public ?string $gpaAtPromotion,
        public ?string $notes,
        public ?int $decidedBy,
        public ?string $idempotencyKey,
    ) {}
}
