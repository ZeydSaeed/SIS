<?php

namespace App\Application\Intelligence\DTOs;

final readonly class RecommendationDetailDTO
{
    /**
     * @param  array<string, mixed>|null  $detection
     */
    public function __construct(
        public int $id,
        public string $code,
        public string $ruleId,
        public int $riskTier,
        public string $actionType,
        public ?string $schemaName,
        public ?string $tableName,
        public string $what,
        public string $why,
        public ?array $evidence,
        public ?array $alternatives,
        public ?array $expectedImpact,
        public ?array $costAnalysis,
        public ?string $rollbackPlan,
        public float $confidence,
        public ?float $contextSimilarity,
        public ?float $blastRadiusScore,
        public string $status,
        public ?string $rejectionReason,
        public ?string $createdAt,
        public ?array $detection,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'rule_id' => $this->ruleId,
            'risk_tier' => $this->riskTier,
            'action_type' => $this->actionType,
            'schema_name' => $this->schemaName,
            'table_name' => $this->tableName,
            'what' => $this->what,
            'why' => $this->why,
            'evidence' => $this->evidence,
            'alternatives' => $this->alternatives,
            'expected_impact' => $this->expectedImpact,
            'cost_analysis' => $this->costAnalysis,
            'rollback_plan' => $this->rollbackPlan,
            'confidence' => $this->confidence,
            'context_similarity' => $this->contextSimilarity,
            'blast_radius_score' => $this->blastRadiusScore,
            'status' => $this->status,
            'rejection_reason' => $this->rejectionReason,
            'created_at' => $this->createdAt,
            'detection' => $this->detection,
        ];
    }
}
