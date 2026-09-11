<?php

namespace App\Domain\Graduation\Repositories;

/**
 * Persistence port for Graduation write path (school + enrollment identity).
 */
interface GraduationWriteRepositoryInterface
{
    /**
     * @return array{student_id:int,academic_year_id:int,school_id:int}|null
     */
    public function findEnrollmentIdentity(int $enrollmentId, int $schoolId): ?array;

    public function findCompletionOutcomeId(int $schoolId, int $enrollmentId): ?int;

    public function insertCompletionOutcome(
        int $schoolId,
        int $enrollmentId,
        int $studentId,
        int $academicYearId,
        ?int $createdBy,
    ): int;

    /**
     * @param  list<array{requirement_definition_version_id:int,result_status:int}>  $requirementResults
     */
    public function insertEvaluationVersion(
        int $schoolId,
        int $completionOutcomeId,
        int $eligibilityPolicyVersionId,
        string $calculationVersion,
        int $eligibilityStatus,
        int $evaluationStatus,
        ?int $createdBy,
        array $requirementResults,
        string $correlationId,
    ): int;

    /**
     * @return array{id:int,school_id:int,completion_outcome_id:int,created_by:?int,enrollment_id:int,eligibility_status:int}|null
     */
    public function findCompletionOutcomeVersion(int $versionId, int $schoolId): ?array;

    public function insertApproval(
        int $schoolId,
        int $enrollmentId,
        int $completionOutcomeVersionId,
        int $attemptNo,
        int $decisionStatus,
        int $decidedBy,
        ?string $decisionReasonRef,
        string $correlationId,
    ): int;

    public function findApprovedApproval(int $approvalId, int $schoolId): ?array;

    public function findAwardId(int $schoolId, int $enrollmentId): ?int;

    /**
     * @return array{award_id:int,award_version_id:int}
     */
    public function insertAwardWithVersion(
        int $schoolId,
        int $enrollmentId,
        int $studentId,
        int $academicYearId,
        int $approvalId,
        int $completionOutcomeVersionId,
        ?int $issuedBy,
        string $correlationId,
    ): array;

    /**
     * @return array{id:int,school_id:int,graduation_award_id:int,lifecycle_status:int}|null
     */
    public function findAwardVersion(int $awardVersionId, int $schoolId): ?array;

    public function revokeAwardVersion(
        int $schoolId,
        int $awardVersionId,
        string $reasonRef,
        ?int $revokedBy,
        string $correlationId,
    ): int;
}
