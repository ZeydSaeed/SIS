<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Graduation\Contracts\GraduationReadRepositoryInterface;
use App\Application\Graduation\Queries\GetRequirementEvaluationsHandler;
use App\Application\Graduation\Queries\GetRequirementEvaluationsQuery;
use App\Domain\Graduation\Exceptions\CompletionOutcomeNotFoundException;
use App\Domain\Graduation\Exceptions\GraduationAuthorityDeniedException;
use App\Security\Context\SchoolContext;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;
use Tests\Support\Database\SeedsGraduationConcurrencyGraph;

final class GetRequirementEvaluationsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use SeedsGraduationConcurrencyGraph;

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $this->app->make(SchoolContext::class)->set($schoolId);
    }

    /**
     * @return array{outcome_id:int,version_id:int,req_ver_a:int,req_ver_b:int,eval_ids:list<int>}
     */
    private function seedOutcomeVersionWithTwoEvaluations(
        array $g,
        int $schoolId,
        int $enrollmentId,
        int $studentId,
        string $codePrefix,
    ): array {
        $this->bindSchool($schoolId);

        $policyId = $g['policy_id'];
        $policyVersionId = $g['policy_version_id'];

        if ($schoolId !== $g['school_a']) {
            $policyId = (int) DB::table('graduation.eligibility_policies')->insertGetId([
                'school_id' => $schoolId,
                'policy_code' => 'PB'.$codePrefix,
                'name' => 'Policy B',
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
            'calculation_version' => 'calc-req-1',
            'evaluated_at' => now(),
            'is_current_official' => true,
            'created_at' => now(),
        ]);

        $reqA = (int) DB::table('graduation.requirement_definitions')->insertGetId([
            'school_id' => $schoolId,
            'eligibility_policy_id' => $policyId,
            'requirement_code' => $codePrefix.'A',
            'name' => 'Req A',
            'status' => 1,
            'created_at' => now(),
        ]);
        $reqVerA = (int) DB::table('graduation.requirement_definition_versions')->insertGetId([
            'school_id' => $schoolId,
            'requirement_definition_id' => $reqA,
            'version_no' => 1,
            'lifecycle_status' => 2,
            'created_at' => now(),
        ]);
        $reqB = (int) DB::table('graduation.requirement_definitions')->insertGetId([
            'school_id' => $schoolId,
            'eligibility_policy_id' => $policyId,
            'requirement_code' => $codePrefix.'B',
            'name' => 'Req B',
            'status' => 1,
            'created_at' => now(),
        ]);
        $reqVerB = (int) DB::table('graduation.requirement_definition_versions')->insertGetId([
            'school_id' => $schoolId,
            'requirement_definition_id' => $reqB,
            'version_no' => 1,
            'lifecycle_status' => 2,
            'created_at' => now(),
        ]);

        $evalIdA = (int) DB::table('graduation.requirement_evaluations')->insertGetId([
            'school_id' => $schoolId,
            'completion_outcome_version_id' => $versionId,
            'requirement_definition_version_id' => $reqVerA,
            'result_status' => 1,
            'evaluated_at' => now(),
            'notes_ref' => null,
        ]);
        $evalIdB = (int) DB::table('graduation.requirement_evaluations')->insertGetId([
            'school_id' => $schoolId,
            'completion_outcome_version_id' => $versionId,
            'requirement_definition_version_id' => $reqVerB,
            'result_status' => 3,
            'evaluated_at' => now(),
            'notes_ref' => 'opaque-note',
        ]);

        return [
            'outcome_id' => $outcomeId,
            'version_id' => $versionId,
            'req_ver_a' => $reqVerA,
            'req_ver_b' => $reqVerB,
            'eval_ids' => [$evalIdA, $evalIdB],
        ];
    }

    #[Test]
    public function returns_multiple_requirement_evaluation_facts_for_preferred_version(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $seed = $this->seedOutcomeVersionWithTwoEvaluations(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            'RA',
        );

        $dto = $this->app->make(GetRequirementEvaluationsHandler::class)->handle(
            new GetRequirementEvaluationsQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertSame($seed['outcome_id'], $dto->completionOutcomeId);
        $this->assertSame($seed['version_id'], $dto->completionOutcomeVersionId);
        $this->assertCount(2, $dto->evaluations);
        $this->assertSame(1, $dto->evaluations[0]->resultStatus);
        $this->assertSame(3, $dto->evaluations[1]->resultStatus);
        $this->assertSame($seed['req_ver_a'], $dto->evaluations[0]->requirementDefinitionVersionId);
        $this->assertSame('opaque-note', $dto->evaluations[1]->notesRef);
    }

    #[Test]
    public function prefers_current_official_version_evaluations_over_older_version(): void
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

        $reqId = (int) DB::table('graduation.requirement_definitions')->insertGetId([
            'school_id' => $g['school_a'],
            'eligibility_policy_id' => $g['policy_id'],
            'requirement_code' => 'PROV1',
            'name' => 'Prov',
            'status' => 1,
            'created_at' => now(),
        ]);
        $reqVer = (int) DB::table('graduation.requirement_definition_versions')->insertGetId([
            'school_id' => $g['school_a'],
            'requirement_definition_id' => $reqId,
            'version_no' => 1,
            'lifecycle_status' => 2,
            'created_at' => now(),
        ]);

        $oldVersion = (int) DB::table('graduation.completion_outcome_versions')->insertGetId([
            'school_id' => $g['school_a'],
            'completion_outcome_id' => $outcomeId,
            'version_no' => 1,
            'lifecycle_status' => 1,
            'evaluation_status' => 2,
            'eligibility_status' => 3,
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

        DB::table('graduation.requirement_evaluations')->insert([
            [
                'school_id' => $g['school_a'],
                'completion_outcome_version_id' => $oldVersion,
                'requirement_definition_version_id' => $reqVer,
                'result_status' => 2,
                'evaluated_at' => now(),
            ],
            [
                'school_id' => $g['school_a'],
                'completion_outcome_version_id' => $officialVersion,
                'requirement_definition_version_id' => $reqVer,
                'result_status' => 1,
                'evaluated_at' => now(),
            ],
        ]);

        $dto = $this->app->make(GetRequirementEvaluationsHandler::class)->handle(
            new GetRequirementEvaluationsQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertSame($officialVersion, $dto->completionOutcomeVersionId);
        $this->assertCount(1, $dto->evaluations);
        $this->assertSame(1, $dto->evaluations[0]->resultStatus);
        $this->assertSame($officialVersion, $dto->evaluations[0]->completionOutcomeVersionId);
    }

    #[Test]
    public function outcome_without_evaluations_returns_empty_list(): void
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

        $dto = $this->app->make(GetRequirementEvaluationsHandler::class)->handle(
            new GetRequirementEvaluationsQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertSame([], $dto->evaluations);
        $this->assertNull($dto->completionOutcomeVersionId);
    }

    #[Test]
    public function missing_outcome_is_not_found(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->bindSchool($g['school_a']);

        $this->expectException(CompletionOutcomeNotFoundException::class);
        $this->app->make(GetRequirementEvaluationsHandler::class)->handle(
            new GetRequirementEvaluationsQuery($g['school_a'], $g['enrollment_a']),
        );
    }

    #[Test]
    public function school_mismatch_is_rejected_by_authority(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->seedOutcomeVersionWithTwoEvaluations(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            'XM',
        );

        $this->bindSchool($g['school_b']);

        $this->expectException(GraduationAuthorityDeniedException::class);
        $this->app->make(GetRequirementEvaluationsHandler::class)->handle(
            new GetRequirementEvaluationsQuery($g['school_a'], $g['enrollment_a']),
        );
    }

    #[Test]
    public function rls_actor_under_school_b_cannot_see_school_a_requirement_evaluations(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();

        $seedA = $this->seedOutcomeVersionWithTwoEvaluations(
            $g,
            $g['school_a'],
            $g['enrollment_a'],
            $g['student_a'],
            'AA',
        );
        $seedB = $this->seedOutcomeVersionWithTwoEvaluations(
            $g,
            $g['school_b'],
            $g['enrollment_b'],
            $g['student_b'],
            'BB',
        );

        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_b']]);

        $visibleIds = collect(DB::select('SELECT id FROM graduation.requirement_evaluations'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($seedA['eval_ids'] as $idA) {
            $this->assertNotContains($idA, $visibleIds);
        }
        foreach ($seedB['eval_ids'] as $idB) {
            $this->assertContains($idB, $visibleIds);
        }

        $repo = $this->app->make(GraduationReadRepositoryInterface::class);
        $this->assertNull($repo->findRequirementEvaluations($g['school_a'], $g['enrollment_a']));

        $b = $repo->findRequirementEvaluations($g['school_b'], $g['enrollment_b']);
        $this->assertNotNull($b);
        $this->assertSame($seedB['outcome_id'], $b->completionOutcomeId);
        $this->assertCount(2, $b->evaluations);

        PostgreSqlRlsActor::reset();
    }
}
