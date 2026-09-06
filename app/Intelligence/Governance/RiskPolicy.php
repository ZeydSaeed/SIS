<?php

namespace App\Intelligence\Governance;

use App\Intelligence\Enums\RiskTier;

class RiskPolicy
{
    public function tierForAction(string $actionType): RiskTier
    {
        if (in_array($actionType, config('intelligence.forbidden_auto_actions', []), true)) {
            return RiskTier::Forbidden;
        }

        return match ($actionType) {
            'analyze', 'cache_ttl_adjust', 'replica_route', 'worker_scale' => RiskTier::SafeAuto,
            'index_add', 'materialized_view', 'add_foreign_key' => RiskTier::HumanReview,
            'partition_review', 'schema_review', 'index_drop', 'normalization_change' => RiskTier::AdrDba,
            default => RiskTier::Observe,
        };
    }

    public function canAutoExecute(RiskTier $tier): bool
    {
        $maxTier = (int) config('intelligence.auto_execute_max_tier', 1);

        return $tier->canAutoExecute($maxTier);
    }

    public function confidenceIsNotAuthorization(float $confidence, RiskTier $tier): bool
    {
        return $confidence >= 0.99 && $tier->requiresApproval();
    }
}
