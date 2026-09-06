<?php

namespace App\Application\Intelligence\DTOs;

final readonly class RecommendationListItemDTO
{
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
        public float $confidence,
        public string $status,
        public ?string $createdAt,
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
            'confidence' => $this->confidence,
            'status' => $this->status,
            'created_at' => $this->createdAt,
        ];
    }
}
