<?php

namespace App\Application\Intelligence\Results;

final readonly class ApproveRecommendationResult
{
    public function __construct(
        public int $recommendationId,
        public ?string $optimizationEventCode = null,
    ) {}
}
