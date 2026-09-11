<?php

namespace App\Application\Graduation\DTOs;

/**
 * Approval attempt set for preferred completion_outcome_version (schema UNIQUE version+attempt).
 */
final readonly class GraduationApprovalsDTO
{
    /**
     * @param  list<GraduationApprovalItemDTO>  $approvals
     */
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $completionOutcomeId,
        public ?int $completionOutcomeVersionId,
        public array $approvals,
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
            'approvals' => array_map(
                static fn (GraduationApprovalItemDTO $item): array => $item->toArray(),
                $this->approvals,
            ),
        ];
    }
}
