<?php

namespace App\Infrastructure\Intelligence;

use App\Application\Intelligence\Contracts\RecommendationCommandPort;
use App\Application\Intelligence\Results\ApproveRecommendationResult;
use App\Intelligence\Governance\ApprovalGate;
use App\Intelligence\Models\Recommendation;
use App\Intelligence\Optimization\SafeAutoExecutor;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RecommendationCommandAdapter implements RecommendationCommandPort
{
    public function __construct(
        private readonly ApprovalGate $approvalGate,
        private readonly SafeAutoExecutor $executor,
    ) {}

    public function approve(int $recommendationId, int $userId, ?string $reason): ApproveRecommendationResult
    {
        $recommendation = $this->findOrFail($recommendationId);

        $this->approvalGate->approve($recommendation, $userId, $reason);

        $event = $this->executor->executeApproved($recommendation->fresh());

        return new ApproveRecommendationResult(
            recommendationId: $recommendationId,
            optimizationEventCode: $event?->event_code,
        );
    }

    public function reject(int $recommendationId, int $userId, string $reason, ?string $choseInstead = null): void
    {
        $recommendation = $this->findOrFail($recommendationId);

        $this->approvalGate->reject($recommendation, $userId, $reason, $choseInstead);
    }

    private function findOrFail(int $recommendationId): Recommendation
    {
        $recommendation = Recommendation::query()->find($recommendationId);

        if ($recommendation === null) {
            throw new NotFoundHttpException('Recommendation not found.');
        }

        return $recommendation;
    }
}
