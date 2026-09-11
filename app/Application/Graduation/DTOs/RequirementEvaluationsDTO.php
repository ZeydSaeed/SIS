<?php

namespace App\Application\Graduation\DTOs;

/**
 * Requirement evaluations for one CompletionOutcome (+ preferred version provenance).
 */
final readonly class RequirementEvaluationsDTO
{
    /**
     * @param  list<RequirementEvaluationItemDTO>  $evaluations
     */
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $completionOutcomeId,
        public ?int $completionOutcomeVersionId,
        public array $evaluations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'school_id' => $this->schoolId,
            'enrollment_id' => $this->enrollmentId,
            'completion_outcome_id' => $this->completionOutcomeId,
            'completion_outcome_version_id' => $this->completionOutcomeVersionId,
            'evaluations' => array_map(
                static fn (RequirementEvaluationItemDTO $item): array => $item->toArray(),
                $this->evaluations,
            ),
        ];
    }
}
