<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Graduation\Commands\ApproveGraduationCommand;
use App\Application\Graduation\Commands\ApproveGraduationHandler;
use App\Application\Graduation\Commands\CreateCompletionOutcomeCommand;
use App\Application\Graduation\Commands\CreateCompletionOutcomeHandler;
use App\Application\Graduation\Commands\EvaluateCompletionCommand;
use App\Application\Graduation\Commands\EvaluateCompletionHandler;
use App\Application\Graduation\Commands\IssueAwardCommand;
use App\Application\Graduation\Commands\IssueAwardHandler;
use App\Application\Graduation\Commands\PublishAwardCommand;
use App\Application\Graduation\Commands\PublishAwardHandler;
use App\Application\Graduation\Commands\RevokeAwardCommand;
use App\Application\Graduation\Commands\RevokeAwardHandler;
use App\Domain\Graduation\Exceptions\EvaluatorApproverConflictException;
use App\Domain\Graduation\Exceptions\GraduationBusinessConflictException;
use App\Domain\Graduation\Exceptions\PublicationPolicyNotConfiguredException;
use App\Security\Context\SchoolContext;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\SeedsGraduationConcurrencyGraph;

final class GraduationWritePathPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use SeedsGraduationConcurrencyGraph;

    protected function connectionsToTransact(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'sis.graduation.authority.create_completion_outcome' => [7, 8],
            'sis.graduation.authority.evaluate_completion' => [7],
            'sis.graduation.authority.approve_graduation' => [8],
            'sis.graduation.authority.issue_award' => [8],
            'sis.graduation.authority.revoke_award' => [8],
        ]);
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $schoolId]);
        $this->app->make(SchoolContext::class)->set($schoolId);
    }

    /**
     * @return array{req_ver_id:int}
     */
    private function seedRequirementVersion(int $schoolId, int $policyId, string $code): array
    {
        $reqId = (int) DB::table('graduation.requirement_definitions')->insertGetId([
            'school_id' => $schoolId,
            'eligibility_policy_id' => $policyId,
            'requirement_code' => $code,
            'name' => 'Req '.$code,
            'status' => 1,
            'created_at' => now(),
        ]);
        $reqVerId = (int) DB::table('graduation.requirement_definition_versions')->insertGetId([
            'school_id' => $schoolId,
            'requirement_definition_id' => $reqId,
            'version_no' => 1,
            'lifecycle_status' => 2,
            'created_at' => now(),
        ]);

        return ['req_ver_id' => $reqVerId];
    }

    #[Test]
    public function create_completion_outcome_is_idempotent_and_unique_per_enrollment(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->bindSchool($g['school_a']);

        $handler = $this->app->make(CreateCompletionOutcomeHandler::class);
        $cmd = new CreateCompletionOutcomeCommand(
            schoolId: $g['school_a'],
            enrollmentId: $g['enrollment_a'],
            actorUserId: 7,
            idempotencyKey: 'grad-create-1',
            correlationId: 'corr-1',
        );

        $first = $handler->handle($cmd);
        $replay = $handler->handle($cmd);

        $this->assertFalse($first->fromIdempotencyCache);
        $this->assertTrue($replay->fromIdempotencyCache);
        $this->assertSame($first->completionOutcomeId, $replay->completionOutcomeId);
        $this->assertSame(1, (int) DB::table('graduation.completion_outcomes')
            ->where('school_id', $g['school_a'])
            ->where('enrollment_id', $g['enrollment_a'])
            ->count());
        $this->assertSame(1, (int) DB::table('audit.outbox_messages')->where('event_type', 'like', '%CompletionOutcomeCreated%')->count());

        $this->expectException(GraduationBusinessConflictException::class);
        $handler->handle(new CreateCompletionOutcomeCommand(
            $g['school_a'],
            $g['enrollment_a'],
            7,
            'grad-create-2',
            'corr-2',
        ));
    }

    #[Test]
    public function evaluator_cannot_approve_own_evaluation(): void
    {
        // Allow-list includes evaluator so SoD is tested (not fail-closed authority).
        config(['sis.graduation.authority.approve_graduation' => [7, 8]]);

        $g = $this->seedGraduationConcurrencyGraph();
        $this->bindSchool($g['school_a']);

        $this->app->make(CreateCompletionOutcomeHandler::class)->handle(new CreateCompletionOutcomeCommand(
            $g['school_a'], $g['enrollment_a'], 7, 'c-out', 'c',
        ));

        $req = $this->seedRequirementVersion($g['school_a'], $g['policy_id'], 'R1');

        $eval = $this->app->make(EvaluateCompletionHandler::class)->handle(new EvaluateCompletionCommand(
            schoolId: $g['school_a'],
            enrollmentId: $g['enrollment_a'],
            eligibilityPolicyVersionId: $g['policy_version_id'],
            requirementResults: [[
                'requirement_definition_version_id' => $req['req_ver_id'],
                'result_status' => 1,
            ]],
            calculationVersion: 'calc-1',
            actorUserId: 7,
            idempotencyKey: 'eval-1',
            correlationId: 'c-eval',
        ));

        $this->expectException(EvaluatorApproverConflictException::class);
        $this->app->make(ApproveGraduationHandler::class)->handle(new ApproveGraduationCommand(
            schoolId: $g['school_a'],
            completionOutcomeVersionId: $eval->completionOutcomeVersionId,
            actorUserId: 7,
            idempotencyKey: 'appr-bad',
            correlationId: 'c-appr',
        ));
    }

    #[Test]
    public function distinct_approver_can_approve_issue_revoke_and_publish_is_gated(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->bindSchool($g['school_a']);

        $this->app->make(CreateCompletionOutcomeHandler::class)->handle(new CreateCompletionOutcomeCommand(
            $g['school_a'], $g['enrollment_a'], 7, 'c-out-2', 'c',
        ));

        $req = $this->seedRequirementVersion($g['school_a'], $g['policy_id'], 'R2');

        $eval = $this->app->make(EvaluateCompletionHandler::class)->handle(new EvaluateCompletionCommand(
            $g['school_a'],
            $g['enrollment_a'],
            $g['policy_version_id'],
            [['requirement_definition_version_id' => $req['req_ver_id'], 'result_status' => 1]],
            'calc-2',
            7,
            'eval-2',
            'c-eval-2',
        ));

        $approved = $this->app->make(ApproveGraduationHandler::class)->handle(new ApproveGraduationCommand(
            $g['school_a'],
            $eval->completionOutcomeVersionId,
            8,
            'appr-ok',
            'c-appr-ok',
        ));

        $issued = $this->app->make(IssueAwardHandler::class)->handle(new IssueAwardCommand(
            $g['school_a'],
            $g['enrollment_a'],
            $approved->approvalId,
            8,
            'issue-1',
            'c-issue',
        ));

        $revoked = $this->app->make(RevokeAwardHandler::class)->handle(new RevokeAwardCommand(
            $g['school_a'],
            $issued->awardVersionId,
            'opaque-reason-ref',
            8,
            'revoke-1',
            'c-revoke',
        ));

        $this->assertNotNull($revoked->revocationId);
        $this->assertSame(1, (int) DB::table('graduation.revocation_records')
            ->where('id', $revoked->revocationId)
            ->count());

        $this->expectException(PublicationPolicyNotConfiguredException::class);
        $this->app->make(PublishAwardHandler::class)->handle(new PublishAwardCommand(
            $g['school_a'],
            $issued->awardVersionId,
            8,
            'pub-1',
            'c-pub',
        ));
    }

    #[Test]
    public function multi_enrollment_outcomes_are_independent(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->bindSchool($g['school_a']);

        // Second enrollment for same student in school A (different class seat).
        $classId = (int) DB::table('enrollment.classes')->where('school_id', $g['school_a'])->value('id');
        $sectionId = (int) DB::table('enrollment.sections')->where('class_id', $classId)->value('id');
        $enrollmentE2 = (int) DB::table('enrollment.enrollments')->insertGetId([
            'student_id' => $g['student_a'],
            'academic_year_id' => $g['year_id'],
            'school_id' => $g['school_a'],
            'class_id' => $classId,
            'section_id' => $sectionId,
            'enrollment_number' => 'E2-'.$g['enrollment_a'],
            'status' => 1,
            'effective_from' => '2025-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $create = $this->app->make(CreateCompletionOutcomeHandler::class);
        $out1 = $create->handle(new CreateCompletionOutcomeCommand(
            $g['school_a'], $g['enrollment_a'], 7, 'me-e1', 'c',
        ));
        $out2 = $create->handle(new CreateCompletionOutcomeCommand(
            $g['school_a'], $enrollmentE2, 7, 'me-e2', 'c',
        ));

        $this->assertNotSame($out1->completionOutcomeId, $out2->completionOutcomeId);
        $this->assertSame(1, (int) DB::table('students.students')->where('id', $g['student_a'])->where('status', 1)->count());
        $this->assertSame(1, (int) DB::table('enrollment.enrollments')->where('id', $enrollmentE2)->where('status', 1)->count());
    }

    #[Test]
    public function cross_school_context_is_rejected(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->bindSchool($g['school_b']);

        $this->expectException(\App\Domain\Graduation\Exceptions\GraduationAuthorityDeniedException::class);
        $this->app->make(CreateCompletionOutcomeHandler::class)->handle(new CreateCompletionOutcomeCommand(
            $g['school_a'],
            $g['enrollment_a'],
            7,
            'cross-1',
            'c',
        ));
    }
}
