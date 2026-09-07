<?php

namespace App\Optimization\Analysis;

final readonly class RootCauseReport
{
    /**
     * @param  array<string, mixed>  $evidence
     * @param  list<string>  $potentialSideEffects
     */
    public function __construct(
        public string $problem,
        public array $evidence,
        public string $rootCause,
        public string $affectedComponent,
        public string $optimizationCandidate,
        public float $expectedImprovementPct,
        public int $riskTier,
        public string $validationMethod,
        public string $rollbackStrategy,
        public array $potentialSideEffects = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'problem' => $this->problem,
            'evidence' => $this->evidence,
            'root_cause' => $this->rootCause,
            'affected_component' => $this->affectedComponent,
            'optimization_candidate' => $this->optimizationCandidate,
            'expected_improvement_pct' => $this->expectedImprovementPct,
            'risk_tier' => $this->riskTier,
            'validation_method' => $this->validationMethod,
            'rollback_strategy' => $this->rollbackStrategy,
            'potential_side_effects' => $this->potentialSideEffects,
        ];
    }
}
