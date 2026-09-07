<?php

namespace Tests\Unit\Optimization\SelfHealing;

use App\Intelligence\Learning\ConfidenceEngine;
use App\Intelligence\Models\Recommendation;
use App\Optimization\SelfHealing\RecommendationRanker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecommendationRankerTest extends TestCase
{
    use RefreshDatabase;
    #[Test]
    public function s16_learning_ranks_recommendations_without_escalating_risk_tier(): void
    {
        config([
            'optimization.scoring.max_risk_tier_autonomous' => 1,
            'optimization.environments.testing.max_risk_tier_autonomous' => 1,
        ]);

        $ranker = new RecommendationRanker(app(ConfidenceEngine::class));

        $this->assertSame(1, $ranker->maxAutonomousRiskTier());

        $highConfidence = new Recommendation([
            'rule_id' => 'rule_analyze_table',
            'confidence' => 0.95,
            'risk_tier' => 1,
            'action_type' => 'analyze',
        ]);
        $lowConfidence = new Recommendation([
            'rule_id' => 'rule_analyze_table',
            'confidence' => 0.5,
            'risk_tier' => 1,
            'action_type' => 'analyze',
        ]);

        $ranked = $ranker->rank(collect([$lowConfidence, $highConfidence]));

        $this->assertSame(0.95, (float) $ranked->first()->confidence);
        $this->assertLessThanOrEqual(1, (int) $ranked->first()->risk_tier);
    }
}
