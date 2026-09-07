<?php

namespace Tests\Unit\Optimization;

use App\Optimization\Analysis\OptimizationScorer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OptimizationScorerTest extends TestCase
{
    #[Test]
    public function it_scores_high_impact_low_risk_candidates_higher(): void
    {
        $scorer = new OptimizationScorer;

        $good = $scorer->score([
            'expected_improvement_pct' => 50,
            'confidence' => 0.9,
            'risk_tier' => 1,
            'complexity' => 1,
        ]);

        $bad = $scorer->score([
            'expected_improvement_pct' => 10,
            'confidence' => 0.5,
            'risk_tier' => 3,
            'complexity' => 3,
        ]);

        $this->assertGreaterThan($bad['score'], $good['score']);
    }

    #[Test]
    public function it_marks_tier_one_analyze_as_autonomous_eligible(): void
    {
        $scorer = new OptimizationScorer;

        $candidate = [
            'expected_improvement_pct' => 30,
            'confidence' => 0.8,
            'risk_tier' => 1,
            'complexity' => 1,
            'action_type' => 'analyze',
        ];

        $score = $scorer->score($candidate);

        $this->assertTrue($scorer->eligibleForAutonomous($candidate, $score['score']));
    }

    #[Test]
    public function it_rejects_high_risk_for_autonomous(): void
    {
        $scorer = new OptimizationScorer;

        $candidate = [
            'expected_improvement_pct' => 80,
            'confidence' => 0.95,
            'risk_tier' => 3,
            'complexity' => 1,
            'action_type' => 'analyze',
        ];

        $score = $scorer->score($candidate);

        $this->assertFalse($scorer->eligibleForAutonomous($candidate, $score['score']));
    }
}
