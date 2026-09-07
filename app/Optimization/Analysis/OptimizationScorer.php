<?php

namespace App\Optimization\Analysis;

final class OptimizationScorer
{
    /**
     * Score = (Expected Benefit × Confidence) / (Risk × Complexity)
     *
     * @param  array<string, mixed>  $candidate
     * @return array{score: float, impact: float, confidence: float, risk: float, complexity: float}
     */
    public function score(array $candidate): array
    {
        $impact = (float) ($candidate['expected_improvement_pct'] ?? 10);
        $confidence = (float) ($candidate['confidence'] ?? 0.5);
        $risk = max(1, (int) ($candidate['risk_tier'] ?? 2));
        $complexity = max(1, (int) ($candidate['complexity'] ?? 2));

        $score = ($impact * $confidence) / ($risk * $complexity);

        return [
            'score' => round($score, 4),
            'impact' => $impact,
            'confidence' => $confidence,
            'risk' => (float) $risk,
            'complexity' => (float) $complexity,
        ];
    }

    public function eligibleForAutonomous(array $candidate, float $score): bool
    {
        $minConfidence = (float) config('optimization.scoring.min_confidence_for_autonomous', 0.75);
        $maxTier = (int) config('optimization.scoring.max_risk_tier_autonomous', 1);

        return $score >= 1.0
            && (float) ($candidate['confidence'] ?? 0) >= $minConfidence
            && (int) ($candidate['risk_tier'] ?? 99) <= $maxTier
            && in_array($candidate['action_type'] ?? '', config('optimization.low_risk_auto_actions', []), true);
    }
}
