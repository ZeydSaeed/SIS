<?php

namespace App\Application\Graduation\DTOs;

/**
 * One persisted graduation_award_versions row for history listing (not pointer-only).
 */
final readonly class OutcomeHistoryAwardVersionDTO
{
    public function __construct(
        public int $versionId,
        public int $versionNo,
        public int $graduationApprovalId,
        public int $completionOutcomeVersionId,
        public int $lifecycleStatus,
        public bool $isCurrentIssued,
        public string $awardedAt,
        public ?int $issuedBy,
        public ?string $awardNumber,
        public ?int $honorsCode,
        public ?int $supersedesVersionId,
        public ?int $supersededByVersionId,
        public ?string $correlationId,
        public string $createdAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version_id' => $this->versionId,
            'version_no' => $this->versionNo,
            'graduation_approval_id' => $this->graduationApprovalId,
            'completion_outcome_version_id' => $this->completionOutcomeVersionId,
            'lifecycle_status' => $this->lifecycleStatus,
            'is_current_issued' => $this->isCurrentIssued,
            'awarded_at' => $this->awardedAt,
            'issued_by' => $this->issuedBy,
            'award_number' => $this->awardNumber,
            'honors_code' => $this->honorsCode,
            'supersedes_version_id' => $this->supersedesVersionId,
            'superseded_by_version_id' => $this->supersededByVersionId,
            'correlation_id' => $this->correlationId,
            'created_at' => $this->createdAt,
        ];
    }
}
