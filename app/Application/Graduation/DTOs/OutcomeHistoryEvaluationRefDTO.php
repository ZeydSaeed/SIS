<?php

namespace App\Application\Graduation\DTOs;

/**
 * Lightweight requirement-evaluation reference for history (not full evaluation graph).
 */
final readonly class OutcomeHistoryEvaluationRefDTO
{
    public function __construct(
        public int $evaluationId,
        public int $requirementDefinitionVersionId,
        public int $resultStatus,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'evaluation_id' => $this->evaluationId,
            'requirement_definition_version_id' => $this->requirementDefinitionVersionId,
            'result_status' => $this->resultStatus,
        ];
    }
}
