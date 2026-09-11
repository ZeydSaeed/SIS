<?php

namespace App\Infrastructure\Persistence\Graduation;

use App\Application\Graduation\Contracts\GraduationReadRepositoryInterface;
use App\Application\Graduation\DTOs\CompletionStatusDTO;
use App\Application\Graduation\DTOs\GraduationApprovalItemDTO;
use App\Application\Graduation\DTOs\GraduationApprovalsDTO;
use App\Application\Graduation\DTOs\GraduationAwardDTO;
use App\Application\Graduation\DTOs\GraduationAwardVersionDTO;
use App\Application\Graduation\DTOs\OutcomeHistoryApprovalRefDTO;
use App\Application\Graduation\DTOs\OutcomeHistoryAwardDTO;
use App\Application\Graduation\DTOs\OutcomeHistoryAwardVersionDTO;
use App\Application\Graduation\DTOs\OutcomeHistoryCompletionDTO;
use App\Application\Graduation\DTOs\OutcomeHistoryCompletionVersionDTO;
use App\Application\Graduation\DTOs\OutcomeHistoryDTO;
use App\Application\Graduation\DTOs\OutcomeHistoryEvaluationRefDTO;
use App\Application\Graduation\DTOs\RequirementEvaluationItemDTO;
use App\Application\Graduation\DTOs\RequirementEvaluationsDTO;
use Illuminate\Support\Facades\DB;

/**
 * Read-only Graduation adapter (3C.19.1–3C.19.5).
 *
 * Completion version preference (3C.19.1–3): is_current_official DESC, version_no DESC.
 * Award version (3C.19.4 / 04A): POINTER-ONLY via current_issued_version_id — no fallback.
 * Outcome history (3C.19.5): ALL versions by version_no ASC — never preferred/pointer collapse.
 */
final class EloquentGraduationReadRepository implements GraduationReadRepositoryInterface
{
    public function findCompletionStatus(int $schoolId, int $enrollmentId): ?CompletionStatusDTO
    {
        $row = DB::selectOne(
            '
            SELECT
                o.id AS completion_outcome_id,
                o.school_id,
                o.enrollment_id,
                o.student_id,
                o.academic_year_id,
                o.current_official_version_id,
                v.id AS version_id,
                v.version_no,
                v.lifecycle_status,
                v.evaluation_status,
                v.eligibility_status,
                v.is_current_official,
                v.eligibility_policy_version_id,
                v.calculation_version,
                v.evaluated_at
            FROM graduation.completion_outcomes AS o
            LEFT JOIN LATERAL (
                SELECT
                    cv.id,
                    cv.version_no,
                    cv.lifecycle_status,
                    cv.evaluation_status,
                    cv.eligibility_status,
                    cv.is_current_official,
                    cv.eligibility_policy_version_id,
                    cv.calculation_version,
                    cv.evaluated_at
                FROM graduation.completion_outcome_versions AS cv
                WHERE cv.completion_outcome_id = o.id
                  AND cv.school_id = o.school_id
                ORDER BY cv.is_current_official DESC, cv.version_no DESC
                LIMIT 1
            ) AS v ON TRUE
            WHERE o.school_id = ?
              AND o.enrollment_id = ?
            ',
            [$schoolId, $enrollmentId],
        );

        if ($row === null) {
            return null;
        }

        $evaluatedAt = $row->evaluated_at ?? null;
        if ($evaluatedAt !== null) {
            $evaluatedAt = (string) $evaluatedAt;
        }

        return new CompletionStatusDTO(
            schoolId: (int) $row->school_id,
            enrollmentId: (int) $row->enrollment_id,
            completionOutcomeId: (int) $row->completion_outcome_id,
            studentId: (int) $row->student_id,
            academicYearId: (int) $row->academic_year_id,
            currentOfficialVersionId: $row->current_official_version_id !== null
                ? (int) $row->current_official_version_id
                : null,
            versionId: $row->version_id !== null ? (int) $row->version_id : null,
            versionNo: $row->version_no !== null ? (int) $row->version_no : null,
            lifecycleStatus: $row->lifecycle_status !== null ? (int) $row->lifecycle_status : null,
            evaluationStatus: $row->evaluation_status !== null ? (int) $row->evaluation_status : null,
            eligibilityStatus: $row->eligibility_status !== null ? (int) $row->eligibility_status : null,
            isCurrentOfficial: $row->is_current_official !== null ? (bool) $row->is_current_official : null,
            eligibilityPolicyVersionId: $row->eligibility_policy_version_id !== null
                ? (int) $row->eligibility_policy_version_id
                : null,
            calculationVersion: $row->calculation_version !== null ? (string) $row->calculation_version : null,
            evaluatedAt: $evaluatedAt,
        );
    }

    public function findRequirementEvaluations(int $schoolId, int $enrollmentId): ?RequirementEvaluationsDTO
    {
        $rows = DB::select(
            '
            SELECT
                o.id AS completion_outcome_id,
                o.school_id,
                o.enrollment_id,
                v.id AS completion_outcome_version_id,
                re.id AS evaluation_id,
                re.school_id AS evaluation_school_id,
                re.completion_outcome_version_id AS evaluation_version_id,
                re.requirement_definition_version_id,
                re.result_status,
                re.evaluated_at,
                re.notes_ref
            FROM graduation.completion_outcomes AS o
            LEFT JOIN LATERAL (
                SELECT cv.id
                FROM graduation.completion_outcome_versions AS cv
                WHERE cv.completion_outcome_id = o.id
                  AND cv.school_id = o.school_id
                ORDER BY cv.is_current_official DESC, cv.version_no DESC
                LIMIT 1
            ) AS v ON TRUE
            LEFT JOIN graduation.requirement_evaluations AS re
                ON re.completion_outcome_version_id = v.id
               AND re.school_id = o.school_id
            WHERE o.school_id = ?
              AND o.enrollment_id = ?
            ORDER BY re.id ASC NULLS LAST
            ',
            [$schoolId, $enrollmentId],
        );

        if ($rows === []) {
            return null;
        }

        $first = $rows[0];
        $versionId = $first->completion_outcome_version_id !== null
            ? (int) $first->completion_outcome_version_id
            : null;

        $evaluations = [];
        foreach ($rows as $row) {
            if ($row->evaluation_id === null) {
                continue;
            }

            $evaluations[] = new RequirementEvaluationItemDTO(
                id: (int) $row->evaluation_id,
                schoolId: (int) $row->evaluation_school_id,
                completionOutcomeVersionId: (int) $row->evaluation_version_id,
                requirementDefinitionVersionId: (int) $row->requirement_definition_version_id,
                resultStatus: (int) $row->result_status,
                evaluatedAt: (string) $row->evaluated_at,
                notesRef: $row->notes_ref !== null ? (string) $row->notes_ref : null,
            );
        }

        return new RequirementEvaluationsDTO(
            schoolId: (int) $first->school_id,
            enrollmentId: (int) $first->enrollment_id,
            completionOutcomeId: (int) $first->completion_outcome_id,
            completionOutcomeVersionId: $versionId,
            evaluations: $evaluations,
        );
    }

    public function findGraduationApprovals(int $schoolId, int $enrollmentId): ?GraduationApprovalsDTO
    {
        $rows = DB::select(
            '
            SELECT
                o.id AS completion_outcome_id,
                o.school_id,
                o.enrollment_id,
                v.id AS completion_outcome_version_id,
                ga.id AS approval_id,
                ga.school_id AS approval_school_id,
                ga.enrollment_id AS approval_enrollment_id,
                ga.completion_outcome_version_id AS approval_version_id,
                ga.attempt_no,
                ga.decision_status,
                ga.requested_at,
                ga.requested_by,
                ga.decided_at,
                ga.decided_by,
                ga.decision_reason_ref,
                ga.correlation_id,
                ga.created_at
            FROM graduation.completion_outcomes AS o
            LEFT JOIN LATERAL (
                SELECT cv.id
                FROM graduation.completion_outcome_versions AS cv
                WHERE cv.completion_outcome_id = o.id
                  AND cv.school_id = o.school_id
                ORDER BY cv.is_current_official DESC, cv.version_no DESC
                LIMIT 1
            ) AS v ON TRUE
            LEFT JOIN graduation.graduation_approvals AS ga
                ON ga.completion_outcome_version_id = v.id
               AND ga.school_id = o.school_id
            WHERE o.school_id = ?
              AND o.enrollment_id = ?
            ORDER BY ga.attempt_no ASC NULLS LAST
            ',
            [$schoolId, $enrollmentId],
        );

        if ($rows === []) {
            return null;
        }

        $first = $rows[0];
        $versionId = $first->completion_outcome_version_id !== null
            ? (int) $first->completion_outcome_version_id
            : null;

        $approvals = [];
        foreach ($rows as $row) {
            if ($row->approval_id === null) {
                continue;
            }

            $approvals[] = new GraduationApprovalItemDTO(
                id: (int) $row->approval_id,
                schoolId: (int) $row->approval_school_id,
                enrollmentId: (int) $row->approval_enrollment_id,
                completionOutcomeVersionId: (int) $row->approval_version_id,
                attemptNo: (int) $row->attempt_no,
                decisionStatus: (int) $row->decision_status,
                requestedAt: (string) $row->requested_at,
                requestedBy: $row->requested_by !== null ? (int) $row->requested_by : null,
                decidedAt: $row->decided_at !== null ? (string) $row->decided_at : null,
                decidedBy: $row->decided_by !== null ? (int) $row->decided_by : null,
                decisionReasonRef: $row->decision_reason_ref !== null ? (string) $row->decision_reason_ref : null,
                correlationId: $row->correlation_id !== null ? (string) $row->correlation_id : null,
                createdAt: (string) $row->created_at,
            );
        }

        return new GraduationApprovalsDTO(
            schoolId: (int) $first->school_id,
            enrollmentId: (int) $first->enrollment_id,
            completionOutcomeId: (int) $first->completion_outcome_id,
            completionOutcomeVersionId: $versionId,
            approvals: $approvals,
        );
    }

    public function findGraduationAward(int $schoolId, int $enrollmentId): ?GraduationAwardDTO
    {
        $row = DB::selectOne(
            '
            SELECT
                a.id AS award_id,
                a.school_id,
                a.enrollment_id,
                a.student_id,
                a.academic_year_id,
                a.specialization_id,
                a.current_issued_version_id,
                a.created_by,
                a.created_at,
                v.id AS award_version_id,
                v.version_no,
                v.graduation_approval_id,
                v.completion_outcome_version_id,
                v.lifecycle_status,
                v.is_current_issued,
                v.awarded_at,
                v.issued_by,
                v.award_number,
                v.honors_code,
                v.supersedes_version_id,
                v.superseded_by_version_id,
                v.correlation_id
            FROM graduation.graduation_awards AS a
            LEFT JOIN graduation.graduation_award_versions AS v
                ON v.id = a.current_issued_version_id
               AND v.school_id = a.school_id
            WHERE a.school_id = ?
              AND a.enrollment_id = ?
            ',
            [$schoolId, $enrollmentId],
        );

        if ($row === null) {
            return null;
        }

        $version = null;
        if ($row->award_version_id !== null) {
            $version = new GraduationAwardVersionDTO(
                awardVersionId: (int) $row->award_version_id,
                versionNo: (int) $row->version_no,
                graduationApprovalId: (int) $row->graduation_approval_id,
                completionOutcomeVersionId: (int) $row->completion_outcome_version_id,
                lifecycleStatus: (int) $row->lifecycle_status,
                isCurrentIssued: (bool) $row->is_current_issued,
                awardedAt: (string) $row->awarded_at,
                issuedBy: $row->issued_by !== null ? (int) $row->issued_by : null,
                awardNumber: $row->award_number !== null ? (string) $row->award_number : null,
                honorsCode: $row->honors_code !== null ? (int) $row->honors_code : null,
                supersedesVersionId: $row->supersedes_version_id !== null
                    ? (int) $row->supersedes_version_id
                    : null,
                supersededByVersionId: $row->superseded_by_version_id !== null
                    ? (int) $row->superseded_by_version_id
                    : null,
                correlationId: $row->correlation_id !== null ? (string) $row->correlation_id : null,
            );
        }

        return new GraduationAwardDTO(
            schoolId: (int) $row->school_id,
            enrollmentId: (int) $row->enrollment_id,
            awardId: (int) $row->award_id,
            studentId: (int) $row->student_id,
            academicYearId: (int) $row->academic_year_id,
            specializationId: $row->specialization_id !== null ? (int) $row->specialization_id : null,
            currentIssuedVersionId: $row->current_issued_version_id !== null
                ? (int) $row->current_issued_version_id
                : null,
            createdBy: $row->created_by !== null ? (int) $row->created_by : null,
            createdAt: (string) $row->created_at,
            version: $version,
        );
    }

    public function findOutcomeHistory(int $schoolId, int $enrollmentId): OutcomeHistoryDTO
    {
        $completion = $this->loadOutcomeHistoryCompletion($schoolId, $enrollmentId);
        $award = $this->loadOutcomeHistoryAward($schoolId, $enrollmentId);

        return new OutcomeHistoryDTO(
            schoolId: $schoolId,
            enrollmentId: $enrollmentId,
            completionOutcome: $completion,
            award: $award,
        );
    }

    private function loadOutcomeHistoryCompletion(int $schoolId, int $enrollmentId): ?OutcomeHistoryCompletionDTO
    {
        $head = DB::selectOne(
            '
            SELECT
                o.id AS completion_outcome_id,
                o.student_id,
                o.academic_year_id,
                o.specialization_id,
                o.current_official_version_id,
                o.created_by,
                o.created_at
            FROM graduation.completion_outcomes AS o
            WHERE o.school_id = ?
              AND o.enrollment_id = ?
            ',
            [$schoolId, $enrollmentId],
        );

        if ($head === null) {
            return null;
        }

        $versionRows = DB::select(
            '
            SELECT
                cv.id AS version_id,
                cv.version_no,
                cv.lifecycle_status,
                cv.evaluation_status,
                cv.eligibility_status,
                cv.is_current_official,
                cv.eligibility_policy_version_id,
                cv.calculation_version,
                cv.source_fingerprint,
                cv.policy_fingerprint,
                cv.evaluated_at,
                cv.supersedes_version_id,
                cv.superseded_by_version_id,
                cv.correlation_id,
                cv.created_at
            FROM graduation.completion_outcome_versions AS cv
            WHERE cv.school_id = ?
              AND cv.completion_outcome_id = ?
            ORDER BY cv.version_no ASC
            ',
            [$schoolId, (int) $head->completion_outcome_id],
        );

        $versionIds = array_map(
            static fn (object $row): int => (int) $row->version_id,
            $versionRows,
        );

        $evaluationRefsByVersion = $this->loadOutcomeHistoryEvaluationRefs($schoolId, $versionIds);
        $approvalRefsByVersion = $this->loadOutcomeHistoryApprovalRefs($schoolId, $versionIds);

        $versions = [];
        foreach ($versionRows as $row) {
            $versionId = (int) $row->version_id;
            $evaluatedAt = $row->evaluated_at !== null ? (string) $row->evaluated_at : null;

            $versions[] = new OutcomeHistoryCompletionVersionDTO(
                versionId: $versionId,
                versionNo: (int) $row->version_no,
                lifecycleStatus: (int) $row->lifecycle_status,
                evaluationStatus: (int) $row->evaluation_status,
                eligibilityStatus: (int) $row->eligibility_status,
                isCurrentOfficial: (bool) $row->is_current_official,
                eligibilityPolicyVersionId: (int) $row->eligibility_policy_version_id,
                calculationVersion: (string) $row->calculation_version,
                sourceFingerprint: $row->source_fingerprint !== null ? (string) $row->source_fingerprint : null,
                policyFingerprint: $row->policy_fingerprint !== null ? (string) $row->policy_fingerprint : null,
                evaluatedAt: $evaluatedAt,
                supersedesVersionId: $row->supersedes_version_id !== null
                    ? (int) $row->supersedes_version_id
                    : null,
                supersededByVersionId: $row->superseded_by_version_id !== null
                    ? (int) $row->superseded_by_version_id
                    : null,
                correlationId: $row->correlation_id !== null ? (string) $row->correlation_id : null,
                createdAt: (string) $row->created_at,
                evaluationRefs: $evaluationRefsByVersion[$versionId] ?? [],
                approvalRefs: $approvalRefsByVersion[$versionId] ?? [],
            );
        }

        return new OutcomeHistoryCompletionDTO(
            completionOutcomeId: (int) $head->completion_outcome_id,
            studentId: (int) $head->student_id,
            academicYearId: (int) $head->academic_year_id,
            specializationId: $head->specialization_id !== null ? (int) $head->specialization_id : null,
            currentOfficialVersionId: $head->current_official_version_id !== null
                ? (int) $head->current_official_version_id
                : null,
            createdBy: $head->created_by !== null ? (int) $head->created_by : null,
            createdAt: (string) $head->created_at,
            versions: $versions,
        );
    }

    /**
     * @param list<int> $versionIds
     * @return array<int, list<OutcomeHistoryEvaluationRefDTO>>
     */
    private function loadOutcomeHistoryEvaluationRefs(int $schoolId, array $versionIds): array
    {
        if ($versionIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($versionIds), '?'));
        $rows = DB::select(
            "
            SELECT
                re.id AS evaluation_id,
                re.completion_outcome_version_id,
                re.requirement_definition_version_id,
                re.result_status
            FROM graduation.requirement_evaluations AS re
            WHERE re.school_id = ?
              AND re.completion_outcome_version_id IN ({$placeholders})
            ORDER BY re.completion_outcome_version_id ASC, re.id ASC
            ",
            [$schoolId, ...$versionIds],
        );

        $grouped = [];
        foreach ($rows as $row) {
            $versionId = (int) $row->completion_outcome_version_id;
            $grouped[$versionId][] = new OutcomeHistoryEvaluationRefDTO(
                evaluationId: (int) $row->evaluation_id,
                requirementDefinitionVersionId: (int) $row->requirement_definition_version_id,
                resultStatus: (int) $row->result_status,
            );
        }

        return $grouped;
    }

    /**
     * @param list<int> $versionIds
     * @return array<int, list<OutcomeHistoryApprovalRefDTO>>
     */
    private function loadOutcomeHistoryApprovalRefs(int $schoolId, array $versionIds): array
    {
        if ($versionIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($versionIds), '?'));
        $rows = DB::select(
            "
            SELECT
                ga.id AS approval_id,
                ga.completion_outcome_version_id,
                ga.attempt_no,
                ga.decision_status
            FROM graduation.graduation_approvals AS ga
            WHERE ga.school_id = ?
              AND ga.completion_outcome_version_id IN ({$placeholders})
            ORDER BY ga.completion_outcome_version_id ASC, ga.attempt_no ASC
            ",
            [$schoolId, ...$versionIds],
        );

        $grouped = [];
        foreach ($rows as $row) {
            $versionId = (int) $row->completion_outcome_version_id;
            $grouped[$versionId][] = new OutcomeHistoryApprovalRefDTO(
                approvalId: (int) $row->approval_id,
                attemptNo: (int) $row->attempt_no,
                decisionStatus: (int) $row->decision_status,
            );
        }

        return $grouped;
    }

    private function loadOutcomeHistoryAward(int $schoolId, int $enrollmentId): ?OutcomeHistoryAwardDTO
    {
        $head = DB::selectOne(
            '
            SELECT
                a.id AS award_id,
                a.student_id,
                a.academic_year_id,
                a.specialization_id,
                a.current_issued_version_id,
                a.created_by,
                a.created_at
            FROM graduation.graduation_awards AS a
            WHERE a.school_id = ?
              AND a.enrollment_id = ?
            ',
            [$schoolId, $enrollmentId],
        );

        if ($head === null) {
            return null;
        }

        $versionRows = DB::select(
            '
            SELECT
                v.id AS version_id,
                v.version_no,
                v.graduation_approval_id,
                v.completion_outcome_version_id,
                v.lifecycle_status,
                v.is_current_issued,
                v.awarded_at,
                v.issued_by,
                v.award_number,
                v.honors_code,
                v.supersedes_version_id,
                v.superseded_by_version_id,
                v.correlation_id,
                v.created_at
            FROM graduation.graduation_award_versions AS v
            WHERE v.school_id = ?
              AND v.graduation_award_id = ?
            ORDER BY v.version_no ASC
            ',
            [$schoolId, (int) $head->award_id],
        );

        $versions = [];
        foreach ($versionRows as $row) {
            $versions[] = new OutcomeHistoryAwardVersionDTO(
                versionId: (int) $row->version_id,
                versionNo: (int) $row->version_no,
                graduationApprovalId: (int) $row->graduation_approval_id,
                completionOutcomeVersionId: (int) $row->completion_outcome_version_id,
                lifecycleStatus: (int) $row->lifecycle_status,
                isCurrentIssued: (bool) $row->is_current_issued,
                awardedAt: (string) $row->awarded_at,
                issuedBy: $row->issued_by !== null ? (int) $row->issued_by : null,
                awardNumber: $row->award_number !== null ? (string) $row->award_number : null,
                honorsCode: $row->honors_code !== null ? (int) $row->honors_code : null,
                supersedesVersionId: $row->supersedes_version_id !== null
                    ? (int) $row->supersedes_version_id
                    : null,
                supersededByVersionId: $row->superseded_by_version_id !== null
                    ? (int) $row->superseded_by_version_id
                    : null,
                correlationId: $row->correlation_id !== null ? (string) $row->correlation_id : null,
                createdAt: (string) $row->created_at,
            );
        }

        return new OutcomeHistoryAwardDTO(
            awardId: (int) $head->award_id,
            studentId: (int) $head->student_id,
            academicYearId: (int) $head->academic_year_id,
            specializationId: $head->specialization_id !== null ? (int) $head->specialization_id : null,
            currentIssuedVersionId: $head->current_issued_version_id !== null
                ? (int) $head->current_issued_version_id
                : null,
            createdBy: $head->created_by !== null ? (int) $head->created_by : null,
            createdAt: (string) $head->created_at,
            versions: $versions,
        );
    }
}
