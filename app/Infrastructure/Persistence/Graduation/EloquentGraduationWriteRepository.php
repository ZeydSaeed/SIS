<?php

namespace App\Infrastructure\Persistence\Graduation;

use App\Database\SchemaHelper;
use App\Domain\Graduation\Exceptions\GraduationBusinessConflictException;
use App\Domain\Graduation\Repositories\GraduationWriteRepositoryInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentGraduationWriteRepository implements GraduationWriteRepositoryInterface
{
    public function findEnrollmentIdentity(int $enrollmentId, int $schoolId): ?array
    {
        $row = DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('id', $enrollmentId)
            ->where('school_id', $schoolId)
            ->first(['id', 'student_id', 'academic_year_id', 'school_id']);

        if ($row === null) {
            return null;
        }

        return [
            'student_id' => (int) $row->student_id,
            'academic_year_id' => (int) $row->academic_year_id,
            'school_id' => (int) $row->school_id,
        ];
    }

    public function findCompletionOutcomeId(int $schoolId, int $enrollmentId): ?int
    {
        $id = DB::table('graduation.completion_outcomes')
            ->where('school_id', $schoolId)
            ->where('enrollment_id', $enrollmentId)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function insertCompletionOutcome(
        int $schoolId,
        int $enrollmentId,
        int $studentId,
        int $academicYearId,
        ?int $createdBy,
    ): int {
        try {
            return (int) DB::table('graduation.completion_outcomes')->insertGetId([
                'school_id' => $schoolId,
                'enrollment_id' => $enrollmentId,
                'student_id' => $studentId,
                'academic_year_id' => $academicYearId,
                'created_by' => $createdBy,
                'created_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw GraduationBusinessConflictException::duplicateIdentity();
            }
            throw $e;
        }
    }

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
    ): int {
        $nextVersion = (int) DB::table('graduation.completion_outcome_versions')
            ->where('completion_outcome_id', $completionOutcomeId)
            ->max('version_no');
        $nextVersion = $nextVersion + 1;

        try {
            $versionId = (int) DB::table('graduation.completion_outcome_versions')->insertGetId([
                'school_id' => $schoolId,
                'completion_outcome_id' => $completionOutcomeId,
                'version_no' => $nextVersion,
                'lifecycle_status' => 1,
                'evaluation_status' => $evaluationStatus,
                'eligibility_status' => $eligibilityStatus,
                'eligibility_policy_version_id' => $eligibilityPolicyVersionId,
                'calculation_version' => $calculationVersion,
                'evaluated_at' => now(),
                'eligibility_determined_at' => now(),
                'is_current_official' => false,
                'created_by' => $createdBy,
                'correlation_id' => $correlationId,
                'created_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw GraduationBusinessConflictException::duplicateIdentity();
            }
            throw $e;
        }

        foreach ($requirementResults as $row) {
            DB::table('graduation.requirement_evaluations')->insert([
                'school_id' => $schoolId,
                'completion_outcome_version_id' => $versionId,
                'requirement_definition_version_id' => (int) $row['requirement_definition_version_id'],
                'result_status' => (int) $row['result_status'],
                'evaluated_at' => now(),
            ]);
        }

        return $versionId;
    }

    public function findCompletionOutcomeVersion(int $versionId, int $schoolId): ?array
    {
        $row = DB::table('graduation.completion_outcome_versions as v')
            ->join('graduation.completion_outcomes as o', 'o.id', '=', 'v.completion_outcome_id')
            ->where('v.id', $versionId)
            ->where('v.school_id', $schoolId)
            ->first([
                'v.id',
                'v.school_id',
                'v.completion_outcome_id',
                'v.created_by',
                'v.eligibility_status',
                'o.enrollment_id',
            ]);

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'school_id' => (int) $row->school_id,
            'completion_outcome_id' => (int) $row->completion_outcome_id,
            'created_by' => $row->created_by !== null ? (int) $row->created_by : null,
            'eligibility_status' => (int) $row->eligibility_status,
            'enrollment_id' => (int) $row->enrollment_id,
        ];
    }

    public function insertApproval(
        int $schoolId,
        int $enrollmentId,
        int $completionOutcomeVersionId,
        int $attemptNo,
        int $decisionStatus,
        int $decidedBy,
        ?string $decisionReasonRef,
        string $correlationId,
    ): int {
        try {
            return (int) DB::table('graduation.graduation_approvals')->insertGetId([
                'school_id' => $schoolId,
                'enrollment_id' => $enrollmentId,
                'completion_outcome_version_id' => $completionOutcomeVersionId,
                'attempt_no' => $attemptNo,
                'decision_status' => $decisionStatus,
                'requested_at' => now(),
                'requested_by' => $decidedBy,
                'decided_at' => now(),
                'decided_by' => $decidedBy,
                'decision_reason_ref' => $decisionReasonRef,
                'correlation_id' => $correlationId,
                'created_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw GraduationBusinessConflictException::duplicateIdentity();
            }
            throw $e;
        }
    }

    public function findApprovedApproval(int $approvalId, int $schoolId): ?array
    {
        $row = DB::table('graduation.graduation_approvals')
            ->where('id', $approvalId)
            ->where('school_id', $schoolId)
            ->where('decision_status', 2)
            ->first();

        if ($row === null) {
            return null;
        }

        return (array) $row;
    }

    public function findAwardId(int $schoolId, int $enrollmentId): ?int
    {
        $id = DB::table('graduation.graduation_awards')
            ->where('school_id', $schoolId)
            ->where('enrollment_id', $enrollmentId)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function insertAwardWithVersion(
        int $schoolId,
        int $enrollmentId,
        int $studentId,
        int $academicYearId,
        int $approvalId,
        int $completionOutcomeVersionId,
        ?int $issuedBy,
        string $correlationId,
    ): array {
        try {
            $awardId = (int) DB::table('graduation.graduation_awards')->insertGetId([
                'school_id' => $schoolId,
                'enrollment_id' => $enrollmentId,
                'student_id' => $studentId,
                'academic_year_id' => $academicYearId,
                'created_by' => $issuedBy,
                'created_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw GraduationBusinessConflictException::duplicateIdentity();
            }
            throw $e;
        }

        $versionId = (int) DB::table('graduation.graduation_award_versions')->insertGetId([
            'school_id' => $schoolId,
            'graduation_award_id' => $awardId,
            'version_no' => 1,
            'graduation_approval_id' => $approvalId,
            'completion_outcome_version_id' => $completionOutcomeVersionId,
            'lifecycle_status' => 1,
            'is_current_issued' => true,
            'awarded_at' => now(),
            'issued_by' => $issuedBy,
            'correlation_id' => $correlationId,
            'created_at' => now(),
        ]);

        DB::table('graduation.graduation_awards')
            ->where('id', $awardId)
            ->update(['current_issued_version_id' => $versionId]);

        return ['award_id' => $awardId, 'award_version_id' => $versionId];
    }

    public function findAwardVersion(int $awardVersionId, int $schoolId): ?array
    {
        $row = DB::table('graduation.graduation_award_versions')
            ->where('id', $awardVersionId)
            ->where('school_id', $schoolId)
            ->first(['id', 'school_id', 'graduation_award_id', 'lifecycle_status', 'is_current_issued']);

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'school_id' => (int) $row->school_id,
            'graduation_award_id' => (int) $row->graduation_award_id,
            'lifecycle_status' => (int) $row->lifecycle_status,
        ];
    }

    public function revokeAwardVersion(
        int $schoolId,
        int $awardVersionId,
        string $reasonRef,
        ?int $revokedBy,
        string $correlationId,
    ): int {
        DB::table('graduation.graduation_award_versions')
            ->where('id', $awardVersionId)
            ->where('school_id', $schoolId)
            ->update([
                'is_current_issued' => false,
                'lifecycle_status' => 3,
            ]);

        return (int) DB::table('graduation.revocation_records')->insertGetId([
            'school_id' => $schoolId,
            'graduation_award_version_id' => $awardVersionId,
            'revoked_at' => now(),
            'revoked_by' => $revokedBy,
            'revocation_reason_ref' => $reasonRef,
            'correlation_id' => $correlationId,
            'created_at' => now(),
        ]);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');

        return $sqlState === '23505' || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
