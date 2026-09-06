<?php

namespace App\Application\Intelligence\Contracts;

use App\Application\Intelligence\Results\ApproveRecommendationResult;

interface RecommendationCommandPort
{
    public function approve(int $recommendationId, int $userId, ?string $reason): ApproveRecommendationResult;

    public function reject(int $recommendationId, int $userId, string $reason, ?string $choseInstead = null): void;
}
