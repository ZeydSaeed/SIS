<?php

namespace App\Application\Graduation\DTOs;

/**
 * Single persisted graduation_approvals row — raw facts only (no status labels).
 */
final readonly class GraduationApprovalItemDTO
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $enrollmentId,
        public int $completionOutcomeVersionId,
        public int $attemptNo,
        public int $decisionStatus,
        public string $requestedAt,
        public ?int $requestedBy,
        public ?string $decidedAt,
        public ?int $decidedBy,
        public ?string $decisionReasonRef,
        public ?string $correlationId,
        public string $createdAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->schoolId,
            'enrollment_id' => $this->enrollmentId,
            'completion_outcome_version_id' => $this->completionOutcomeVersionId,
            'attempt_no' => $this->attemptNo,
            'decision_status' => $this->decisionStatus,
            'requested_at' => $this->requestedAt,
            'requested_by' => $this->requestedBy,
            'decided_at' => $this->decidedAt,
            'decided_by' => $this->decidedBy,
            'decision_reason_ref' => $this->decisionReasonRef,
            'correlation_id' => $this->correlationId,
            'created_at' => $this->createdAt,
        ];
    }
}
