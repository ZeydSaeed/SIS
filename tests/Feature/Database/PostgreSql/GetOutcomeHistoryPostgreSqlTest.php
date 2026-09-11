<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Graduation\Contracts\GraduationReadRepositoryInterface;
use App\Application\Graduation\Queries\GetOutcomeHistoryHandler;
use App\Application\Graduation\Queries\GetOutcomeHistoryQuery;
use App\Domain\Graduation\Exceptions\GraduationAuthorityDeniedException;
use App\Security\Context\SchoolContext;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;
use Tests\Support\Database\SeedsGraduationConcurrencyGraph;

final class GetOutcomeHistoryPostgreSqlTest extends PostgreSqlIntegrationTestCase
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
     *     policy_id:int,
     *     policy_version_id:int,
     *     version_ids:list<int>,
     *     eval_by_version:array<int, list<int>>,
     *     approval_by_version:array<int, list<array{id:int,attempt_no:int}>>
     * }
     */
    private function seedMultiVersionCompletionHistory(array $g, string $suffix): array
    {
        $schoolId = $g['school_a'];
        $enrollmentId = $g['enrollment_a'];
        $studentId = $g['student_a'];
        $this->bindSchool($schoolId);

        $outcomeId = (int) DB::table('graduation.completion_outcomes')->insertGetId([
            'school_id' => $schoolId,
            'enrollment_id' => $enrollmentId,
            'student_id' => $studentId,
            'academic_year_id' => $g['year_id'],
            'created_at' => now(),
        ]);

        $reqId = (int) DB::table('graduation.requirement_definitions')->insertGetId([
            'school_id' => $schoolId,
            'eligibility_policy_id' => $g['policy_id'],
            'requirement_code' => 'H'.$suffix,
            'name' => 'Hist Req '.$suffix,
            'status' => 1,
            'created_at' => now(),
        ]);
        $reqVer = (int) DB::table('graduation.requirement_definition_versions')->insertGetId([
            'school_id' => $schoolId,
            'requirement_definition_id' => $reqId,
            'version_no' => 1,
            'lifecycle_status' => 2,
            'created_at' => now(),
        ]);

        $versionIds = [];
        $evalByVersion = [];
        $approvalByVersion = [];

        // Insert versions out of chronological insert order to prove ORDER BY version_no ASC
        foreach ([2, 1, 3] as $versionNo) {
            $isOfficial = $versionNo === 3;
            $versionId = (int) DB::table('graduation.completion_outcome_versions')->insertGetId([
                'school_id' => $schoolId,
                'completion_outcome_id' => $outcomeId,
                'version_no' => $versionNo,
                'lifecycle_status' => $isOfficial ? 2 : 1,
                'evaluation_status' => 2,
                'eligibility_status' => 2,
                'eligibility_policy_version_id' => $g['policy_version_id'],
                'calculation_version' => 'calc-h-'.$versionNo,
                'evaluated_at' => now(),
                'is_current_official' => $isOfficial,
                'supersedes_version_id' => null,
                'superseded_by_version_id' => null,
                'correlation_id' => 'corr-cv-'.$suffix.'-v'.$versionNo,
                'created_at' => now(),
            ]);
            $versionIds[$versionNo] = $versionId;

            $evalId = (int) DB::table('graduation.requirement_evaluations')->insertGetId([
                'school_id' => $schoolId,
                'completion_outcome_version_id' => $versionId,
                'requirement_definition_version_id' => $reqVer,
                'result_status' => $versionNo,
                'evaluated_at' => now(),
            ]);
            $evalByVersion[$versionId] = [$evalId];

            $attempts = [];
            // Insert attempt 2 before attempt 1 to prove attempt_no ASC ordering
            foreach ([2, 1] as $attemptNo) {
                $approvalId = (int) DB::table('graduation.graduation_approvals')->insertGetId([
                    'school_id' => $schoolId,
                    'enrollment_id' => $enrollmentId,
                    'completion_outcome_version_id' => $versionId,
                    'attempt_no' => $attemptNo,
                    'decision_status' => $attemptNo === 2 ? 2 : 1,
                    'requested_at' => now(),
                    'requested_by' => 7,
                    'decided_at' => $attemptNo === 2 ? now() : null,
                    'decided_by' => $attemptNo === 2 ? 8 : null,
                    'correlation_id' => 'corr-ap-'.$suffix.'-v'.$versionNo.'-a'.$attemptNo,
                    'created_at' => now(),
                ]);
                $attempts[] = ['id' => $approvalId, 'attempt_no' => $attemptNo];
            }
            $approvalByVersion[$versionId] = $attempts;
        }

        return [
            'outcome_id' => $outcomeId,
            'policy_id' => $g['policy_id'],
            'policy_version_id' => $g['policy_version_id'],
            'version_ids' => [$versionIds[1], $versionIds[2], $versionIds[3]],
            'eval_by_version' => $evalByVersion,
            'approval_by_version' => $approvalByVersion,
        ];
    }

    /**
     * @return array{outcome_id:int,version_id:int,approval_id:int,policy_version_id:int}
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
    public function returns_all_completion_versions_ordered_by_version_no_asc_including_non_current(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $seed = $this->seedMultiVersionCompletionHistory($g, 'MV');

        $dto = $this->app->make(GetOutcomeHistoryHandler::class)->handle(
            new GetOutcomeHistoryQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertNotNull($dto->completionOutcome);
        $this->assertSame($seed['outcome_id'], $dto->completionOutcome->completionOutcomeId);
        $this->assertCount(3, $dto->completionOutcome->versions);
        $this->assertSame([1, 2, 3], array_map(
            static fn ($v) => $v->versionNo,
            $dto->completionOutcome->versions,
        ));
        $this->assertSame($seed['version_ids'], array_map(
            static fn ($v) => $v->versionId,
            $dto->completionOutcome->versions,
        ));

        $this->assertFalse($dto->completionOutcome->versions[0]->isCurrentOfficial);
        $this->assertFalse($dto->completionOutcome->versions[1]->isCurrentOfficial);
        $this->assertTrue($dto->completionOutcome->versions[2]->isCurrentOfficial);

        foreach ($dto->completionOutcome->versions as $version) {
            $this->assertNull($version->supersedesVersionId);
            $this->assertNull($version->supersededByVersionId);
            $this->assertCount(1, $version->evaluationRefs);
            $this->assertSame(
                $seed['eval_by_version'][$version->versionId][0],
                $version->evaluationRefs[0]->evaluationId,
            );
            $this->assertSame($version->versionNo, $version->evaluationRefs[0]->resultStatus);

            $this->assertCount(2, $version->approvalRefs);
            $this->assertSame([1, 2], array_map(
                static fn ($a) => $a->attemptNo,
                $version->approvalRefs,
            ));
        }
    }

    #[Test]
    public function returns_all_award_versions_as_peer_ordered_by_version_no_asc_without_pointer_collapse(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $base = $this->seedOutcomeApproval($g, $g['school_a'], $g['enrollment_a'], $g['student_a'], 'AW');

        $v2 = $this->seedAwardWithVersion(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            $base['approval_id'],
            $base['version_id'],
            'AW',
            setPointer: false,
            lifecycleStatus: 1,
            isCurrentIssued: true,
            versionNo: 2,
        );
        $v1 = $this->seedAwardWithVersion(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            $base['approval_id'],
            $base['version_id'],
            'AW',
            setPointer: false,
            lifecycleStatus: 3,
            isCurrentIssued: false,
            versionNo: 1,
            awardId: $v2['award_id'],
        );
        $v3 = $this->seedAwardWithVersion(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            $base['approval_id'],
            $base['version_id'],
            'AW',
            setPointer: true,
            lifecycleStatus: 2,
            isCurrentIssued: false,
            versionNo: 3,
            awardId: $v2['award_id'],
        );

        $dto = $this->app->make(GetOutcomeHistoryHandler::class)->handle(
            new GetOutcomeHistoryQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertNotNull($dto->award);
        $this->assertSame($v2['award_id'], $dto->award->awardId);
        $this->assertSame($v3['version_id'], $dto->award->currentIssuedVersionId);
        $this->assertCount(3, $dto->award->versions);
        $this->assertSame([1, 2, 3], array_map(
            static fn ($v) => $v->versionNo,
            $dto->award->versions,
        ));
        $this->assertSame($v1['version_id'], $dto->award->versions[0]->versionId);
        $this->assertSame(3, $dto->award->versions[0]->lifecycleStatus);
        $this->assertFalse($dto->award->versions[0]->isCurrentIssued);
        $this->assertSame($v2['version_id'], $dto->award->versions[1]->versionId);
        $this->assertSame($v3['version_id'], $dto->award->versions[2]->versionId);
    }

    #[Test]
    public function revoked_award_versions_remain_in_history(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $base = $this->seedOutcomeApproval($g, $g['school_a'], $g['enrollment_a'], $g['student_a'], 'RV');

        $revoked = $this->seedAwardWithVersion(
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

        $dto = $this->app->make(GetOutcomeHistoryHandler::class)->handle(
            new GetOutcomeHistoryQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertNotNull($dto->award);
        $this->assertCount(1, $dto->award->versions);
        $this->assertSame($revoked['version_id'], $dto->award->versions[0]->versionId);
        $this->assertSame(3, $dto->award->versions[0]->lifecycleStatus);
        $this->assertFalse($dto->award->versions[0]->isCurrentIssued);
    }

    #[Test]
    public function missing_completion_with_existing_award_returns_null_completion_and_award(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->bindSchool($g['school_a']);

        // Award identity has no FK to completion_outcomes — head-only is schema-legal.
        $awardId = (int) DB::table('graduation.graduation_awards')->insertGetId([
            'school_id' => $g['school_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'academic_year_id' => $g['year_id'],
            'created_by' => 8,
            'created_at' => now(),
        ]);

        $dto = $this->app->make(GetOutcomeHistoryHandler::class)->handle(
            new GetOutcomeHistoryQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertNull($dto->completionOutcome);
        $this->assertNotNull($dto->award);
        $this->assertSame($awardId, $dto->award->awardId);
        $this->assertSame([], $dto->award->versions);
    }

    #[Test]
    public function neither_completion_nor_award_returns_empty_aggregate(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->bindSchool($g['school_a']);

        $dto = $this->app->make(GetOutcomeHistoryHandler::class)->handle(
            new GetOutcomeHistoryQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertSame($g['school_a'], $dto->schoolId);
        $this->assertSame($g['enrollment_a'], $dto->enrollmentId);
        $this->assertNull($dto->completionOutcome);
        $this->assertNull($dto->award);
    }

    #[Test]
    public function school_b_handler_cannot_read_school_a_history(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->seedMultiVersionCompletionHistory($g, 'XM');

        $this->bindSchool($g['school_b']);

        $this->expectException(GraduationAuthorityDeniedException::class);
        $this->app->make(GetOutcomeHistoryHandler::class)->handle(
            new GetOutcomeHistoryQuery($g['school_a'], $g['enrollment_a']),
        );
    }

    #[Test]
    public function rls_actor_under_school_b_cannot_see_school_a_history(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $seedA = $this->seedMultiVersionCompletionHistory($g, 'AA');

        $baseB = $this->seedOutcomeApproval($g, $g['school_b'], $g['enrollment_b'], $g['student_b'], 'BB');

        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_b']]);

        $visibleOutcomeIds = collect(DB::select('SELECT id FROM graduation.completion_outcomes'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertNotContains($seedA['outcome_id'], $visibleOutcomeIds);
        $this->assertContains($baseB['outcome_id'], $visibleOutcomeIds);

        $repo = $this->app->make(GraduationReadRepositoryInterface::class);
        $a = $repo->findOutcomeHistory($g['school_a'], $g['enrollment_a']);
        $this->assertNull($a->completionOutcome);
        $this->assertNull($a->award);

        $b = $repo->findOutcomeHistory($g['school_b'], $g['enrollment_b']);
        $this->assertNotNull($b->completionOutcome);
        $this->assertSame($baseB['outcome_id'], $b->completionOutcome->completionOutcomeId);

        PostgreSqlRlsActor::reset();
    }

    #[Test]
    public function does_not_reconstruct_supersession_from_version_order(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->seedMultiVersionCompletionHistory($g, 'SS');

        $dto = $this->app->make(GetOutcomeHistoryHandler::class)->handle(
            new GetOutcomeHistoryQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertNotNull($dto->completionOutcome);
        foreach ($dto->completionOutcome->versions as $version) {
            $this->assertNull($version->supersedesVersionId);
            $this->assertNull($version->supersededByVersionId);
        }

        $payload = $dto->toArray();
        $this->assertArrayNotHasKey('supersession_edges', $payload);
        $this->assertArrayNotHasKey('reconstructed_chain', $payload);
        $this->assertArrayNotHasKey('student_status', $payload);
        $this->assertArrayNotHasKey('sod_valid', $payload);
    }
}
