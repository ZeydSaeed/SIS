<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Graduation\Contracts\GraduationReadRepositoryInterface;
use App\Application\Graduation\Queries\GetGraduationAwardHandler;
use App\Application\Graduation\Queries\GetGraduationAwardQuery;
use App\Domain\Graduation\Exceptions\GraduationAuthorityDeniedException;
use App\Security\Context\SchoolContext;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;
use Tests\Support\Database\SeedsGraduationConcurrencyGraph;

final class GetGraduationAwardPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use SeedsGraduationConcurrencyGraph;

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $this->app->make(SchoolContext::class)->set($schoolId);
    }

    /**
     * @return array{
     *     outcome_id:int,
     *     version_id:int,
     *     approval_id:int,
     *     policy_version_id:int
     * }
     */
    private function seedOutcomeApproval(array $g, int $schoolId, int $enrollmentId, int $studentId, string $suffix): array
    {
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
            'calculation_version' => 'calc-award',
            'evaluated_at' => now(),
            'is_current_official' => true,
            'created_at' => now(),
        ]);

        $approvalId = (int) DB::table('graduation.graduation_approvals')->insertGetId([
            'school_id' => $schoolId,
            'enrollment_id' => $enrollmentId,
            'completion_outcome_version_id' => $versionId,
            'attempt_no' => 1,
            'decision_status' => 2,
            'requested_at' => now(),
            'requested_by' => 7,
            'decided_at' => now(),
            'decided_by' => 8,
            'correlation_id' => 'corr-appr-'.$suffix,
            'created_at' => now(),
        ]);

        return [
            'outcome_id' => $outcomeId,
            'version_id' => $versionId,
            'approval_id' => $approvalId,
            'policy_version_id' => $policyVersionId,
        ];
    }

    /**
     * @return array{award_id:int,version_id:int}
     */
    private function seedAwardWithVersion(
        array $g,
        int $schoolId,
        int $enrollmentId,
        int $studentId,
        int $approvalId,
        int $completionOutcomeVersionId,
        string $suffix,
        bool $setPointer = true,
        int $lifecycleStatus = 1,
        bool $isCurrentIssued = true,
        int $versionNo = 1,
        ?int $awardId = null,
    ): array {
        $this->bindSchool($schoolId);

        if ($awardId === null) {
            $awardId = (int) DB::table('graduation.graduation_awards')->insertGetId([
                'school_id' => $schoolId,
                'enrollment_id' => $enrollmentId,
                'student_id' => $studentId,
                'academic_year_id' => $g['year_id'],
                'created_by' => 8,
                'created_at' => now(),
            ]);
        }

        $versionId = (int) DB::table('graduation.graduation_award_versions')->insertGetId([
            'school_id' => $schoolId,
            'graduation_award_id' => $awardId,
            'version_no' => $versionNo,
            'graduation_approval_id' => $approvalId,
            'completion_outcome_version_id' => $completionOutcomeVersionId,
            'lifecycle_status' => $lifecycleStatus,
            'is_current_issued' => $isCurrentIssued,
            'awarded_at' => now(),
            'issued_by' => 8,
            'correlation_id' => 'corr-aw-'.$suffix.'-v'.$versionNo,
            'created_at' => now(),
        ]);

        if ($setPointer) {
            DB::table('graduation.graduation_awards')
                ->where('id', $awardId)
                ->update(['current_issued_version_id' => $versionId]);
        }

        return ['award_id' => $awardId, 'version_id' => $versionId];
    }

    #[Test]
    public function school_a_can_read_school_a_award_with_pointed_version(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $base = $this->seedOutcomeApproval($g, $g['school_a'], $g['enrollment_a'], $g['student_a'], 'A1');
        $award = $this->seedAwardWithVersion(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            $base['approval_id'],
            $base['version_id'],
            'A1',
        );

        $dto = $this->app->make(GetGraduationAwardHandler::class)->handle(
            new GetGraduationAwardQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertNotNull($dto);
        $this->assertSame($award['award_id'], $dto->awardId);
        $this->assertSame($award['version_id'], $dto->currentIssuedVersionId);
        $this->assertNotNull($dto->version);
        $this->assertSame($award['version_id'], $dto->version->awardVersionId);
        $this->assertSame(1, $dto->version->lifecycleStatus);
        $this->assertTrue($dto->version->isCurrentIssued);
        $this->assertSame($base['approval_id'], $dto->version->graduationApprovalId);
        $this->assertSame($base['version_id'], $dto->version->completionOutcomeVersionId);
    }

    #[Test]
    public function school_b_handler_cannot_read_school_a_award(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $base = $this->seedOutcomeApproval($g, $g['school_a'], $g['enrollment_a'], $g['student_a'], 'XM');
        $this->seedAwardWithVersion(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            $base['approval_id'],
            $base['version_id'],
            'XM',
        );

        $this->bindSchool($g['school_b']);

        $this->expectException(GraduationAuthorityDeniedException::class);
        $this->app->make(GetGraduationAwardHandler::class)->handle(
            new GetGraduationAwardQuery($g['school_a'], $g['enrollment_a']),
        );
    }

    #[Test]
    public function rls_actor_under_school_b_cannot_see_school_a_award(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();

        $baseA = $this->seedOutcomeApproval($g, $g['school_a'], $g['enrollment_a'], $g['student_a'], 'AA');
        $awardA = $this->seedAwardWithVersion(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            $baseA['approval_id'],
            $baseA['version_id'],
            'AA',
        );

        $baseB = $this->seedOutcomeApproval($g, $g['school_b'], $g['enrollment_b'], $g['student_b'], 'BB');
        $awardB = $this->seedAwardWithVersion(
            $g,
            $g['school_b'],
            $g['enrollment_b'],
            $g['student_b'],
            $baseB['approval_id'],
            $baseB['version_id'],
            'BB',
        );

        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_b']]);

        $visibleIds = collect(DB::select('SELECT id FROM graduation.graduation_awards'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertNotContains($awardA['award_id'], $visibleIds);
        $this->assertContains($awardB['award_id'], $visibleIds);

        $repo = $this->app->make(GraduationReadRepositoryInterface::class);
        $this->assertNull($repo->findGraduationAward($g['school_a'], $g['enrollment_a']));

        $b = $repo->findGraduationAward($g['school_b'], $g['enrollment_b']);
        $this->assertNotNull($b);
        $this->assertSame($awardB['award_id'], $b->awardId);
        $this->assertSame($awardB['version_id'], $b->version?->awardVersionId);

        PostgreSqlRlsActor::reset();
    }

    #[Test]
    public function pointer_resolves_to_exactly_the_pointed_version(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $base = $this->seedOutcomeApproval($g, $g['school_a'], $g['enrollment_a'], $g['student_a'], 'PTR');

        $v1 = $this->seedAwardWithVersion(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            $base['approval_id'],
            $base['version_id'],
            'PTR',
            setPointer: false,
            lifecycleStatus: 2,
            isCurrentIssued: false,
            versionNo: 1,
        );

        $v2 = $this->seedAwardWithVersion(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            $base['approval_id'],
            $base['version_id'],
            'PTR',
            setPointer: true,
            lifecycleStatus: 1,
            isCurrentIssued: true,
            versionNo: 2,
            awardId: $v1['award_id'],
        );

        $dto = $this->app->make(GetGraduationAwardHandler::class)->handle(
            new GetGraduationAwardQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertNotNull($dto);
        $this->assertSame($v2['version_id'], $dto->currentIssuedVersionId);
        $this->assertSame($v2['version_id'], $dto->version?->awardVersionId);
        $this->assertSame(2, $dto->version?->versionNo);
        $this->assertNotSame($v1['version_id'], $dto->version?->awardVersionId);
    }

    #[Test]
    public function null_pointer_returns_award_identity_with_null_version(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $base = $this->seedOutcomeApproval($g, $g['school_a'], $g['enrollment_a'], $g['student_a'], 'NP');
        $award = $this->seedAwardWithVersion(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            $base['approval_id'],
            $base['version_id'],
            'NP',
            setPointer: false,
            lifecycleStatus: 1,
            isCurrentIssued: true,
        );

        $dto = $this->app->make(GetGraduationAwardHandler::class)->handle(
            new GetGraduationAwardQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertNotNull($dto);
        $this->assertSame($award['award_id'], $dto->awardId);
        $this->assertNull($dto->currentIssuedVersionId);
        $this->assertNull($dto->version);
    }

    #[Test]
    public function negative_null_pointer_does_not_fallback_to_is_current_issued_version(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $base = $this->seedOutcomeApproval($g, $g['school_a'], $g['enrollment_a'], $g['student_a'], 'NF');

        $v1 = $this->seedAwardWithVersion(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            $base['approval_id'],
            $base['version_id'],
            'NF',
            setPointer: false,
            lifecycleStatus: 1,
            isCurrentIssued: true,
            versionNo: 1,
        );

        $this->seedAwardWithVersion(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            $base['approval_id'],
            $base['version_id'],
            'NF',
            setPointer: false,
            lifecycleStatus: 2,
            isCurrentIssued: false,
            versionNo: 2,
            awardId: $v1['award_id'],
        );

        DB::table('graduation.graduation_awards')
            ->where('id', $v1['award_id'])
            ->update(['current_issued_version_id' => null]);

        $dto = $this->app->make(GetGraduationAwardHandler::class)->handle(
            new GetGraduationAwardQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertNotNull($dto);
        $this->assertSame($v1['award_id'], $dto->awardId);
        $this->assertNull($dto->currentIssuedVersionId);
        $this->assertNull($dto->version);
    }

    #[Test]
    public function stale_revoked_pointer_returns_raw_pointed_facts_not_other_current_version(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $base = $this->seedOutcomeApproval($g, $g['school_a'], $g['enrollment_a'], $g['student_a'], 'RV');

        $v1 = $this->seedAwardWithVersion(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            $base['approval_id'],
            $base['version_id'],
            'RV',
            setPointer: true,
            lifecycleStatus: 3,
            isCurrentIssued: false,
            versionNo: 1,
        );

        $v2 = $this->seedAwardWithVersion(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            $base['approval_id'],
            $base['version_id'],
            'RV',
            setPointer: false,
            lifecycleStatus: 1,
            isCurrentIssued: true,
            versionNo: 2,
            awardId: $v1['award_id'],
        );

        DB::table('graduation.graduation_awards')
            ->where('id', $v1['award_id'])
            ->update(['current_issued_version_id' => $v1['version_id']]);

        $dto = $this->app->make(GetGraduationAwardHandler::class)->handle(
            new GetGraduationAwardQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertNotNull($dto);
        $this->assertSame($v1['version_id'], $dto->currentIssuedVersionId);
        $this->assertNotNull($dto->version);
        $this->assertSame($v1['version_id'], $dto->version->awardVersionId);
        $this->assertSame(3, $dto->version->lifecycleStatus);
        $this->assertFalse($dto->version->isCurrentIssued);
        $this->assertNotSame($v2['version_id'], $dto->version->awardVersionId);
    }

    #[Test]
    public function multiple_versions_do_not_cause_arbitrary_fallback_when_pointer_null(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $base = $this->seedOutcomeApproval($g, $g['school_a'], $g['enrollment_a'], $g['student_a'], 'MV');

        $award = $this->seedAwardWithVersion(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            $base['approval_id'],
            $base['version_id'],
            'MV',
            setPointer: false,
            lifecycleStatus: 2,
            isCurrentIssued: false,
            versionNo: 1,
        );

        $this->seedAwardWithVersion(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            $base['approval_id'],
            $base['version_id'],
            'MV',
            setPointer: false,
            lifecycleStatus: 1,
            isCurrentIssued: true,
            versionNo: 3,
            awardId: $award['award_id'],
        );

        $this->seedAwardWithVersion(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            $base['approval_id'],
            $base['version_id'],
            'MV',
            setPointer: false,
            lifecycleStatus: 2,
            isCurrentIssued: false,
            versionNo: 2,
            awardId: $award['award_id'],
        );

        $dto = $this->app->make(GetGraduationAwardHandler::class)->handle(
            new GetGraduationAwardQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertNotNull($dto);
        $this->assertNull($dto->version);
        $this->assertNull($dto->currentIssuedVersionId);
    }

    #[Test]
    public function returns_null_when_no_award_exists(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->bindSchool($g['school_a']);

        $dto = $this->app->make(GetGraduationAwardHandler::class)->handle(
            new GetGraduationAwardQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertNull($dto);
    }
}
