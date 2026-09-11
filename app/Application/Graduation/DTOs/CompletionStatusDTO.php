<?php

namespace App\Application\Graduation\DTOs;

/**
 * Factual CompletionOutcome (+ preferred version snapshot) — no invented institutional labels.
 */
final readonly class CompletionStatusDTO
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $completionOutcomeId,
        public int $studentId,
        public int $academicYearId,
        public ?int $currentOfficialVersionId,
        public ?int $versionId,
        public ?int $versionNo,
        public ?int $lifecycleStatus,
        public ?int $evaluationStatus,
        public ?int $eligibilityStatus,
        public ?bool $isCurrentOfficial,
        public ?int $eligibilityPolicyVersionId,
        public ?string $calculationVersion,
        public ?string $evaluatedAt,
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
            'student_id' => $this->studentId,
            'academic_year_id' => $this->academicYearId,
            'current_official_version_id' => $this->currentOfficialVersionId,
            'version_id' => $this->versionId,
            'version_no' => $this->versionNo,
            'lifecycle_status' => $this->lifecycleStatus,
            'evaluation_status' => $this->evaluationStatus,
            'eligibility_status' => $this->eligibilityStatus,
            'is_current_official' => $this->isCurrentOfficial,
            'eligibility_policy_version_id' => $this->eligibilityPolicyVersionId,
            'calculation_version' => $this->calculationVersion,
            'evaluated_at' => $this->evaluatedAt,
        ];
    }
}
