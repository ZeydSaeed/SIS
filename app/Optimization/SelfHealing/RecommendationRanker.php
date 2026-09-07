<?php

namespace App\Optimization\SelfHealing;

use App\Intelligence\Learning\ConfidenceEngine;
use App\Intelligence\Models\Recommendation;
use Illuminate\Support\Collection;

/**
 * Ranks recommendations using historical confidence — never escalates risk tier.
 */
final class RecommendationRanker
{
    public function __construct(
        private readonly ConfidenceEngine $confidenceEngine,
    ) {}

    /**
     * @param  Collection<int, Recommendation>  $candidates
     * @return Collection<int, Recommendation>
     */
    public function rank(Collection $candidates): Collection
    {
        return $candidates->sortByDesc(function (Recommendation $recommendation) {
            $learned = $this->confidenceEngine->calculateForRule((string) ($recommendation->rule_id ?? 'unknown'));
            $stored = (float) ($recommendation->confidence ?? 0);

            return ($stored * 0.6) + ($learned * 0.4);
        })->values();
    }

    public function maxAutonomousRiskTier(): int
    {
        $env = config('app.env', 'production');
        $envConfig = config("optimization.environments.{$env}", []);
        $envTier = (int) ($envConfig['max_risk_tier_autonomous'] ?? config('optimization.scoring.max_risk_tier_autonomous', 1));

        return min($envTier, (int) config('optimization.scoring.max_risk_tier_autonomous', 1));
    }
}
