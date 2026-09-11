<?php

namespace App\Application\Graduation\DTOs;

/**
 * One persisted completion_outcome_versions row + lightweight child refs.
 *
 * @phpstan-type EvaluationRefList list<OutcomeHistoryEvaluationRefDTO>
 * @phpstan-type ApprovalRefList list<OutcomeHistoryApprovalRefDTO>
 */
final readonly class OutcomeHistoryCompletionVersionDTO
{
    /**
     * @param list<OutcomeHistoryEvaluationRefDTO> $evaluationRefs
     * @param list<OutcomeHistoryApprovalRefDTO> $approvalRefs
     */
    public function __construct(
        public int $versionId,
        public int $versionNo,
        public int $lifecycleStatus,
        public int $evaluationStatus,
        public int $eligibilityStatus,
        public bool $isCurrentOfficial,
        public int $eligibilityPolicyVersionId,
        public string $calculationVersion,
        public ?string $sourceFingerprint,
        public ?string $policyFingerprint,
        public ?string $evaluatedAt,
        public ?int $supersedesVersionId,
        public ?int $supersededByVersionId,
        public ?string $correlationId,
        public string $createdAt,
        public array $evaluationRefs,
        public array $approvalRefs,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version_id' => $this->versionId,
            'version_no' => $this->versionNo,
            'lifecycle_status' => $this->lifecycleStatus,
            'evaluation_status' => $this->evaluationStatus,
            'eligibility_status' => $this->eligibilityStatus,
            'is_current_official' => $this->isCurrentOfficial,
            'eligibility_policy_version_id' => $this->eligibilityPolicyVersionId,
            'calculation_version' => $this->calculationVersion,
            'source_fingerprint' => $this->sourceFingerprint,
            'policy_fingerprint' => $this->policyFingerprint,
            'evaluated_at' => $this->evaluatedAt,
            'supersedes_version_id' => $this->supersedesVersionId,
            'superseded_by_version_id' => $this->supersededByVersionId,
            'correlation_id' => $this->correlationId,
            'created_at' => $this->createdAt,
            'evaluation_refs' => array_map(
                static fn (OutcomeHistoryEvaluationRefDTO $r) => $r->toArray(),
                $this->evaluationRefs,
            ),
            'approval_refs' => array_map(
                static fn (OutcomeHistoryApprovalRefDTO $r) => $r->toArray(),
                $this->approvalRefs,
            ),
        ];
    }
}
