<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Graduation\Contracts\GraduationReadRepositoryInterface;
use App\Application\Graduation\Queries\GetGraduationApprovalHandler;
use App\Application\Graduation\Queries\GetGraduationApprovalQuery;
use App\Domain\Graduation\Exceptions\CompletionOutcomeNotFoundException;
use App\Domain\Graduation\Exceptions\GraduationAuthorityDeniedException;
use App\Security\Context\SchoolContext;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;
use Tests\Support\Database\SeedsGraduationConcurrencyGraph;

final class GetGraduationApprovalPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use SeedsGraduationConcurrencyGraph;

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $this->app->make(SchoolContext::class)->set($schoolId);
    }

    /**
     * @return array{outcome_id:int,version_id:int,approval_ids:list<int>,policy_version_id:int}
     */
    private function seedOutcomeWithApprovals(
        array $g,
        int $schoolId,
        int $enrollmentId,
        int $studentId,
        string $suffix,
        int $attemptCount = 2,
    ): array {
        $this->bindSchool($schoolId);

        $policyVersionId = $g['policy_version_id'];
        if ($schoolId !== $g['school_a']) {
            $policyId = (int) DB::table('graduation.eligibility_policies')->insertGetId([
                'school_id' => $schoolId,
                'policy_code' => 'P'.$suffix,
                'name' => 'Policy '.$suffix,
                'status' => 1,
                'created_at' => now(),
            ]);
            $policyVersionId = (int) DB::table('graduation.eligibility_policy_versions')->insertGetId([
                'school_id' => $schoolId,
                'eligibility_policy_id' => $policyId,
                'version_no' => 1,
                'lifecycle_status' => 2,
                'is_current_effective' => true,
                'created_at' => now(),
            ]);
        }

        $outcomeId = (int) DB::table('graduation.completion_outcomes')->insertGetId([
            'school_id' => $schoolId,
            'enrollment_id' => $enrollmentId,
            'student_id' => $studentId,
            'academic_year_id' => $g['year_id'],
            'created_at' => now(),
        ]);

        $versionId = (int) DB::table('graduation.completion_outcome_versions')->insertGetId([
            'school_id' => $schoolId,
            'completion_outcome_id' => $outcomeId,
            'version_no' => 1,
            'lifecycle_status' => 1,
            'evaluation_status' => 2,
            'eligibility_status' => 2,
            'eligibility_policy_version_id' => $policyVersionId,
            'calculation_version' => 'calc-appr',
            'evaluated_at' => now(),
            'is_current_official' => true,
            'created_at' => now(),
        ]);

        $approvalIds = [];
        for ($attempt = 1; $attempt <= $attemptCount; $attempt++) {
            $approvalIds[] = (int) DB::table('graduation.graduation_approvals')->insertGetId([
                'school_id' => $schoolId,
                'enrollment_id' => $enrollmentId,
                'completion_outcome_version_id' => $versionId,
                'attempt_no' => $attempt,
                'decision_status' => $attempt === $attemptCount ? 2 : 1,
                'requested_at' => now(),
                'requested_by' => 7,
                'decided_at' => $attempt === $attemptCount ? now() : null,
                'decided_by' => $attempt === $attemptCount ? 8 : null,
                'decision_reason_ref' => null,
                'correlation_id' => 'corr-'.$suffix.'-'.$attempt,
                'created_at' => now(),
            ]);
        }

        return [
            'outcome_id' => $outcomeId,
            'version_id' => $versionId,
            'approval_ids' => $approvalIds,
            'policy_version_id' => $policyVersionId,
        ];
    }

    #[Test]
    public function returns_approval_attempt_facts_for_preferred_version(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $seed = $this->seedOutcomeWithApprovals(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            'A1',
            2,
        );

        $dto = $this->app->make(GetGraduationApprovalHandler::class)->handle(
            new GetGraduationApprovalQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertSame($seed['outcome_id'], $dto->completionOutcomeId);
        $this->assertSame($seed['version_id'], $dto->completionOutcomeVersionId);
        $this->assertCount(2, $dto->approvals);
        $this->assertSame(1, $dto->approvals[0]->attemptNo);
        $this->assertSame(1, $dto->approvals[0]->decisionStatus);
        $this->assertSame(2, $dto->approvals[1]->attemptNo);
        $this->assertSame(2, $dto->approvals[1]->decisionStatus);
        $this->assertSame(8, $dto->approvals[1]->decidedBy);
    }

    #[Test]
    public function prefers_official_version_approvals_not_older_version(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->bindSchool($g['school_a']);

        $outcomeId = (int) DB::table('graduation.completion_outcomes')->insertGetId([
            'school_id' => $g['school_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'academic_year_id' => $g['year_id'],
            'created_at' => now(),
        ]);

        $oldVersion = (int) DB::table('graduation.completion_outcome_versions')->insertGetId([
            'school_id' => $g['school_a'],
            'completion_outcome_id' => $outcomeId,
            'version_no' => 1,
            'lifecycle_status' => 1,
            'evaluation_status' => 2,
            'eligibility_status' => 2,
            'eligibility_policy_version_id' => $g['policy_version_id'],
            'calculation_version' => 'old',
            'evaluated_at' => now(),
            'is_current_official' => false,
            'created_at' => now(),
        ]);
        $officialVersion = (int) DB::table('graduation.completion_outcome_versions')->insertGetId([
            'school_id' => $g['school_a'],
            'completion_outcome_id' => $outcomeId,
            'version_no' => 2,
            'lifecycle_status' => 1,
            'evaluation_status' => 2,
            'eligibility_status' => 2,
            'eligibility_policy_version_id' => $g['policy_version_id'],
            'calculation_version' => 'official',
            'evaluated_at' => now(),
            'is_current_official' => true,
            'created_at' => now(),
        ]);

        DB::table('graduation.graduation_approvals')->insert([
            [
                'school_id' => $g['school_a'],
                'enrollment_id' => $g['enrollment_a'],
                'completion_outcome_version_id' => $oldVersion,
                'attempt_no' => 1,
                'decision_status' => 2,
                'requested_at' => now(),
                'requested_by' => 7,
                'decided_at' => now(),
                'decided_by' => 8,
                'created_at' => now(),
            ],
            [
                'school_id' => $g['school_a'],
                'enrollment_id' => $g['enrollment_a'],
                'completion_outcome_version_id' => $officialVersion,
                'attempt_no' => 1,
                'decision_status' => 1,
                'requested_at' => now(),
                'requested_by' => 7,
                'decided_at' => null,
                'decided_by' => null,
                'created_at' => now(),
            ],
        ]);

        $dto = $this->app->make(GetGraduationApprovalHandler::class)->handle(
            new GetGraduationApprovalQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertSame($officialVersion, $dto->completionOutcomeVersionId);
        $this->assertCount(1, $dto->approvals);
        $this->assertSame(1, $dto->approvals[0]->decisionStatus);
        $this->assertSame($officialVersion, $dto->approvals[0]->completionOutcomeVersionId);
    }

    #[Test]
    public function outcome_without_approvals_returns_empty_list(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->bindSchool($g['school_a']);

        DB::table('graduation.completion_outcomes')->insert([
            'school_id' => $g['school_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'academic_year_id' => $g['year_id'],
            'created_at' => now(),
        ]);

        $dto = $this->app->make(GetGraduationApprovalHandler::class)->handle(
            new GetGraduationApprovalQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertSame([], $dto->approvals);
        $this->assertNull($dto->completionOutcomeVersionId);
    }

    #[Test]
    public function missing_outcome_is_not_found(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->bindSchool($g['school_a']);

        $this->expectException(CompletionOutcomeNotFoundException::class);
        $this->app->make(GetGraduationApprovalHandler::class)->handle(
            new GetGraduationApprovalQuery($g['school_a'], $g['enrollment_a']),
        );
    }

    #[Test]
    public function school_mismatch_is_rejected_by_authority(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->seedOutcomeWithApprovals($g, $g['school_a'], $g['enrollment_a'], $g['student_a'], 'XM', 1);

        $this->bindSchool($g['school_b']);

        $this->expectException(GraduationAuthorityDeniedException::class);
        $this->app->make(GetGraduationApprovalHandler::class)->handle(
            new GetGraduationApprovalQuery($g['school_a'], $g['enrollment_a']),
        );
    }

    #[Test]
    public function rls_actor_under_school_b_cannot_see_school_a_approvals(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();

        $seedA = $this->seedOutcomeWithApprovals($g, $g['school_a'], $g['enrollment_a'], $g['student_a'], 'AA', 1);
        $seedB = $this->seedOutcomeWithApprovals($g, $g['school_b'], $g['enrollment_b'], $g['student_b'], 'BB', 1);

        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_b']]);

        $visibleIds = collect(DB::select('SELECT id FROM graduation.graduation_approvals'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($seedA['approval_ids'] as $idA) {
            $this->assertNotContains($idA, $visibleIds);
        }
        foreach ($seedB['approval_ids'] as $idB) {
            $this->assertContains($idB, $visibleIds);
        }

        $repo = $this->app->make(GraduationReadRepositoryInterface::class);
        $this->assertNull($repo->findGraduationApprovals($g['school_a'], $g['enrollment_a']));

        $b = $repo->findGraduationApprovals($g['school_b'], $g['enrollment_b']);
        $this->assertNotNull($b);
        $this->assertSame($seedB['outcome_id'], $b->completionOutcomeId);
        $this->assertCount(1, $b->approvals);

        PostgreSqlRlsActor::reset();
    }
}
