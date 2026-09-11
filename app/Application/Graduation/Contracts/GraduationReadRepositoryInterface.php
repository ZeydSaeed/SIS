<?php

namespace App\Application\Graduation\Contracts;

use App\Application\Graduation\DTOs\CompletionStatusDTO;
use App\Application\Graduation\DTOs\GraduationAwardDTO;
use App\Application\Graduation\DTOs\GraduationApprovalsDTO;
use App\Application\Graduation\DTOs\OutcomeHistoryDTO;
use App\Application\Graduation\DTOs\RequirementEvaluationsDTO;

/**
 * Graduation read port — query units only; no write methods.
 */
interface GraduationReadRepositoryInterface
{
    public function findCompletionStatus(int $schoolId, int $enrollmentId): ?CompletionStatusDTO;

    /**
     * @return RequirementEvaluationsDTO|null null when CompletionOutcome identity is absent
     */
    public function findRequirementEvaluations(int $schoolId, int $enrollmentId): ?RequirementEvaluationsDTO;

    /**
     * Approval attempts for preferred completion_outcome_version (3C.19.1 preference).
     *
     * @return GraduationApprovalsDTO|null null when CompletionOutcome identity is absent
     */
    public function findGraduationApprovals(int $schoolId, int $enrollmentId): ?GraduationApprovalsDTO;

    /**
     * Award identity for school+enrollment (0..1). Version snapshot is pointer-only
     * (current_issued_version_id); null pointer ⇒ award with null version — no fallback.
     *
     * @return GraduationAwardDTO|null null when award identity is absent
     */
    public function findGraduationAward(int $schoolId, int $enrollmentId): ?GraduationAwardDTO;

    /**
     * Enrollment-scoped historical aggregate (3C.19.5). Always returns a DTO;
     * missing completion and/or award yield null sections — never filters by
     * is_current_official / current_issued_version_id.
     */
    public function findOutcomeHistory(int $schoolId, int $enrollmentId): OutcomeHistoryDTO;
}
