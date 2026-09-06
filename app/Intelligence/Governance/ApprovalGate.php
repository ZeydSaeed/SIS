<?php

namespace App\Intelligence\Governance;

use App\Intelligence\Enums\RecommendationStatus;
use App\Intelligence\Enums\RiskTier;
use App\Intelligence\Models\HumanFeedbackEvent;
use App\Intelligence\Models\Recommendation;

class ApprovalGate
{
    public function __construct(
        private readonly RiskPolicy $riskPolicy,
    ) {}

    public function requiresApproval(Recommendation $recommendation): bool
    {
        $tier = RiskTier::from((int) $recommendation->risk_tier);

        return $tier->requiresApproval() || ! $this->riskPolicy->canAutoExecute($tier);
    }

    public function approve(Recommendation $recommendation, int $userId, ?string $reason = null): Recommendation
    {
        $recommendation->update([
            'status' => RecommendationStatus::Approved->value,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        HumanFeedbackEvent::query()->create([
            'recommendation_id' => $recommendation->id,
            'human_decision' => 'approved',
            'human_decision_reason' => $reason,
            'user_id' => $userId,
            'correlation_id' => $recommendation->correlation_id,
        ]);

        return $recommendation->fresh();
    }

    public function reject(Recommendation $recommendation, int $userId, string $reason, ?string $choseInstead = null): Recommendation
    {
        $recommendation->update([
            'status' => RecommendationStatus::Rejected->value,
            'rejection_reason' => $reason,
        ]);

        HumanFeedbackEvent::query()->create([
            'recommendation_id' => $recommendation->id,
            'human_decision' => 'rejected',
            'human_decision_reason' => $reason,
            'human_chose_instead' => $choseInstead,
            'user_id' => $userId,
            'correlation_id' => $recommendation->correlation_id,
        ]);

        return $recommendation->fresh();
    }
}
