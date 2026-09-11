<?php

namespace App\Application\Graduation\DTOs;

/**
 * Single persisted requirement_evaluations row — raw facts only.
 */
final readonly class RequirementEvaluationItemDTO
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $completionOutcomeVersionId,
        public int $requirementDefinitionVersionId,
        public int $resultStatus,
        public string $evaluatedAt,
        public ?string $notesRef,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->schoolId,
            'completion_outcome_version_id' => $this->completionOutcomeVersionId,
            'requirement_definition_version_id' => $this->requirementDefinitionVersionId,
            'result_status' => $this->resultStatus,
            'evaluated_at' => $this->evaluatedAt,
            'notes_ref' => $this->notesRef,
        ];
    }
}
