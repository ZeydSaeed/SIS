<?php

namespace Tests\Feature\Exams;

use App\Application\Exams\Commands\PresentExamEnrollmentCommand;
use App\Application\Exams\Commands\PresentExamEnrollmentHandler;
use App\Application\Exams\Commands\UpdateExamEnrollmentCommand;
use App\Application\Exams\Commands\UpdateExamEnrollmentHandler;
use App\Database\SchemaHelper;
use App\Domain\Exams\Events\ExamEnrollmentUpdated;
use App\Domain\Exams\Exceptions\ExamAuthorityDeniedException;
use App\Domain\Exams\Exceptions\ExamUpdateForbiddenException;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Exceptions\IdempotencyPayloadConflictException;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Security\Context\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Exams\ExamRepositoryGradeLookupFailingDecorator;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;
use Tests\TestCase;

final class PresentExamEnrollmentCommandTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function confirmed_in_progress_becomes_present_with_outbox_and_idempotency_replay(): void
    {
        $schoolId = $this->createSchool('SCH-72-U09-1', 'Present School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedConfirmedInProgress($schoolId, 'U091');
        $handler = $this->app->make(PresentExamEnrollmentHandler::class);

        $gradesBefore = $this->gradeCount($schoolId);
        $attendanceBefore = $this->attendanceCount($schoolId);

        $result = $handler->handle($this->cmd($schoolId, $graph, (int) $user->id, 'u09-present-1'));

        $this->assertTrue($result->success);
        $this->assertFalse($result->noop);
        $this->assertSame(ExamEnrollmentStatus::Present->value, $result->status);
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_enrollments'), [
            'id' => $graph['exam_enrollment_id'],
            'status' => ExamEnrollmentStatus::Present->value,
        ]);

        $payload = json_decode(
            (string) DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamEnrollmentUpdated::class)
                ->where('payload', 'like', '%exam_enrollment_present%')
                ->orderByDesc('id')
                ->value('payload'),
            true,
        );
        $this->assertSame('exam_enrollment_present', $payload['cause'] ?? null);
        $this->assertSame(ExamEnrollmentStatus::Confirmed->value, $payload['previous_status'] ?? null);
        $this->assertSame(ExamEnrollmentStatus::Present->value, $payload['status'] ?? null);

        $this->assertSame($gradesBefore, $this->gradeCount($schoolId));
        $this->assertSame($attendanceBefore, $this->attendanceCount($schoolId));

        $replay = $handler->handle($this->cmd($schoolId, $graph, (int) $user->id, 'u09-present-1'));
        $this->assertTrue($replay->fromIdempotencyCache);
        $this->assertSame(
            1,
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamEnrollmentUpdated::class)
                ->where('payload', 'like', '%"exam_enrollment_id":'.$graph['exam_enrollment_id'].'%')
                ->where('payload', 'like', '%exam_enrollment_present%')
                ->count()
        );
    }

    #[Test]
    public function already_present_is_idempotent_noop_without_second_event(): void
    {
        $schoolId = $this->createSchool('SCH-72-U09-2', 'Present Noop School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedConfirmedInProgress($schoolId, 'U092');
        $handler = $this->app->make(PresentExamEnrollmentHandler::class);

        $handler->handle($this->cmd($schoolId, $graph, (int) $user->id, 'u09-first'));
        $eventsAfterFirst = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
            ->where('event_type', ExamEnrollmentUpdated::class)
            ->where('payload', 'like', '%exam_enrollment_present%')
            ->count();

        $noop = $handler->handle($this->cmd($schoolId, $graph, (int) $user->id, 'u09-second-key'));
        $this->assertTrue($noop->success);
        $this->assertTrue($noop->noop);
        $this->assertSame(ExamEnrollmentStatus::Present->value, $noop->status);
        $this->assertSame(
            $eventsAfterFirst,
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamEnrollmentUpdated::class)
                ->where('payload', 'like', '%exam_enrollment_present%')
                ->count()
        );
    }

    #[Test]
    public function registered_absent_and_withdrawn_are_denied(): void
    {
        $schoolId = $this->createSchool('SCH-72-U09-3', 'Deny Status School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $handler = $this->app->make(PresentExamEnrollmentHandler::class);

        foreach ([
            [ExamEnrollmentStatus::Registered, 'reg'],
            [ExamEnrollmentStatus::Absent, 'abs'],
            [ExamEnrollmentStatus::Withdrawn, 'wd'],
        ] as [$status, $suffix]) {
            $graph = $this->seedConfirmedInProgress($schoolId, 'U093'.$suffix);
            DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
                ->where('id', $graph['exam_enrollment_id'])
                ->update(['status' => $status->value]);

            try {
                $handler->handle($this->cmd($schoolId, $graph, (int) $user->id, 'u09-deny-'.$suffix));
                $this->fail("Expected denial for {$status->name}");
            } catch (ExamUpdateForbiddenException) {
                $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_enrollments'), [
                    'id' => $graph['exam_enrollment_id'],
                    'status' => $status->value,
                ]);
            }
        }
    }

    #[Test]
    public function confirmed_with_scheduled_completed_or_cancelled_session_is_denied(): void
    {
        $schoolId = $this->createSchool('SCH-72-U09-4', 'Session Deny School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $handler = $this->app->make(PresentExamEnrollmentHandler::class);

        foreach ([
            [ExamSessionStatus::Scheduled, 'sched'],
            [ExamSessionStatus::Completed, 'comp'],
            [ExamSessionStatus::Cancelled, 'canc'],
        ] as [$sessionStatus, $suffix]) {
            $graph = $this->seedConfirmedInProgress($schoolId, 'U094'.$suffix);
            DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
                ->where('id', $graph['session_id'])
                ->update(['status' => $sessionStatus->value]);

            try {
                $handler->handle($this->cmd($schoolId, $graph, (int) $user->id, 'u09-sess-'.$suffix));
                $this->fail("Expected denial for session {$sessionStatus->name}");
            } catch (ExamValidationException) {
                $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_enrollments'), [
                    'id' => $graph['exam_enrollment_id'],
                    'status' => ExamEnrollmentStatus::Confirmed->value,
                ]);
            }
        }
    }

    #[Test]
    public function present_with_non_in_progress_session_is_denied(): void
    {
        $schoolId = $this->createSchool('SCH-72-U09-4b', 'Present Bad Session');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedConfirmedInProgress($schoolId, 'U094b');
        DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
            ->where('id', $graph['exam_enrollment_id'])
            ->update(['status' => ExamEnrollmentStatus::Present->value]);
        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::Cancelled->value]);

        $this->expectException(ExamValidationException::class);
        $this->app->make(PresentExamEnrollmentHandler::class)->handle(
            $this->cmd($schoolId, $graph, (int) $user->id, 'u09-present-cancelled'),
        );
    }

    #[Test]
    public function no_grade_historical_and_current_grade_all_allow_present(): void
    {
        $schoolId = $this->createSchool('SCH-72-U09-5', 'Grade Allow School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $handler = $this->app->make(PresentExamEnrollmentHandler::class);

        $noGrade = $this->seedConfirmedInProgress($schoolId, 'U095a');
        $ok = $handler->handle($this->cmd($schoolId, $noGrade, (int) $user->id, 'u09-no-grade'));
        $this->assertSame(ExamEnrollmentStatus::Present->value, $ok->status);

        $hist = $this->seedConfirmedInProgress($schoolId, 'U095b');
        $this->insertGrade($hist, $schoolId, isCurrent: false);
        $okHist = $handler->handle($this->cmd($schoolId, $hist, (int) $user->id, 'u09-hist'));
        $this->assertSame(ExamEnrollmentStatus::Present->value, $okHist->status);

        $current = $this->seedConfirmedInProgress($schoolId, 'U095c');
        $this->insertGrade($current, $schoolId, isCurrent: true);
        $gradesBefore = $this->gradeCount($schoolId);
        $okCurrent = $handler->handle($this->cmd($schoolId, $current, (int) $user->id, 'u09-current'));
        $this->assertSame(ExamEnrollmentStatus::Present->value, $okCurrent->status);
        $this->assertSame($gradesBefore, $this->gradeCount($schoolId));
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'student_grades'), [
            'exam_enrollment_id' => $current['exam_enrollment_id'],
            'is_current' => true,
            'score' => 70,
        ]);
    }

    #[Test]
    public function grade_lookup_failure_fails_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-U09-6', 'Grade Fail School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedConfirmedInProgress($schoolId, 'U096');

        $this->app->instance(
            ExamRepositoryInterface::class,
            new ExamRepositoryGradeLookupFailingDecorator($this->app->make(ExamRepositoryInterface::class)),
        );

        $outboxBefore = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count();

        try {
            $this->app->make(PresentExamEnrollmentHandler::class)->handle(
                $this->cmd($schoolId, $graph, (int) $user->id, 'u09-grade-fail'),
            );
            $this->fail('Expected grade lookup fail-closed');
        } catch (ExamValidationException $e) {
            $this->assertStringContainsString('grade state could not be determined', $e->getMessage());
        }

        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_enrollments'), [
            'id' => $graph['exam_enrollment_id'],
            'status' => ExamEnrollmentStatus::Confirmed->value,
        ]);
        $this->assertSame($outboxBefore, DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count());
    }

    #[Test]
    public function missing_authorization_and_unauthorized_role_fail_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-U09-7', 'Auth Deny School');
        $teacher = $this->actingAsGradesTeacher(schoolId: $schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedConfirmedInProgress($schoolId, 'U097');

        $this->expectException(ExamAuthorityDeniedException::class);
        $this->app->make(PresentExamEnrollmentHandler::class)->handle(
            $this->cmd($schoolId, $graph, (int) $teacher->id, 'u09-denied'),
        );
    }

    #[Test]
    public function missing_school_context_fails_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-U09-7b', 'No Context School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $graph = $this->seedConfirmedInProgress($schoolId, 'U097b');
        // intentionally no bindSchool

        $this->expectException(ExamAuthorityDeniedException::class);
        $this->app->make(PresentExamEnrollmentHandler::class)->handle(
            $this->cmd($schoolId, $graph, (int) $user->id, 'u09-no-ctx'),
        );
    }

    #[Test]
    public function cross_school_fails_closed_without_mutation_or_outbox(): void
    {
        $schoolA = $this->createSchool('SCH-72-U09-A', 'Present School A');
        $schoolB = $this->createSchool('SCH-72-U09-B', 'Present School B');
        $user = $this->actingAsGradesManagerForSchool($schoolA);
        $this->bindSchool($schoolA);
        $graphB = $this->seedConfirmedInProgress($schoolB, 'U09B');

        $outboxBefore = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count();
        $idempBefore = DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))->count();

        try {
            $this->app->make(PresentExamEnrollmentHandler::class)->handle(new PresentExamEnrollmentCommand(
                schoolId: $schoolA,
                examEnrollmentId: $graphB['exam_enrollment_id'],
                examSessionId: $graphB['session_id'],
                enrollmentId: $graphB['enrollment_id'],
                actorUserId: (int) $user->id,
                idempotencyKey: 'u09-cross',
            ));
            $this->fail('Expected cross-school rejection');
        } catch (ExamValidationException) {
            $this->assertTrue(true);
        }

        $this->assertSame($outboxBefore, DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count());
        $this->assertSame($idempBefore, DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))->count());
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_enrollments'), [
            'id' => $graphB['exam_enrollment_id'],
            'status' => ExamEnrollmentStatus::Confirmed->value,
        ]);
    }

    #[Test]
    public function idempotency_conflict_fails_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-U09-8', 'Idemp Conflict Present');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $first = $this->seedConfirmedInProgress($schoolId, 'U098a');
        $second = $this->seedConfirmedInProgress($schoolId, 'U098b');
        $handler = $this->app->make(PresentExamEnrollmentHandler::class);

        $handler->handle($this->cmd($schoolId, $first, (int) $user->id, 'u09-conflict'));

        $this->expectException(IdempotencyPayloadConflictException::class);
        $handler->handle($this->cmd($schoolId, $second, (int) $user->id, 'u09-conflict'));
    }

    #[Test]
    public function u07_still_rejects_confirmed_to_present(): void
    {
        $schoolId = $this->createSchool('SCH-72-U09-9', 'U07 Regression School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedConfirmedInProgress($schoolId, 'U099');

        $this->expectException(ExamUpdateForbiddenException::class);
        $this->app->make(UpdateExamEnrollmentHandler::class)->handle(new UpdateExamEnrollmentCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            examSessionId: $graph['session_id'],
            enrollmentId: $graph['enrollment_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u09-u07-present',
            status: ExamEnrollmentStatus::Present->value,
            seatNumber: null,
            seatNumberProvided: false,
        ));
    }

    #[Test]
    public function present_permission_remains_on_grades_manager_only(): void
    {
        $this->assertArrayHasKey('exam.enrollment.present', config('security.permissions'));
        $this->assertContains('exam.enrollment.present', config('security.roles.grades_manager'));
        $this->assertNotContains('exam.enrollment.present', config('security.roles.grades_teacher'));
        $this->assertArrayNotHasKey('exam.session.cancel', config('security.permissions'));
    }

    /**
     * @return array{
     *   exam_enrollment_id:int,session_id:int,enrollment_id:int,
     *   year_id:int,student_id:int,subject_id:int
     * }
     */
    private function seedConfirmedInProgress(int $schoolId, string $suffix): array
    {
        $graph = $this->seedExamGradeGraph($schoolId, suffix: $suffix);
        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::InProgress->value]);
        DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
            ->where('id', $graph['exam_enrollment_id'])
            ->update(['status' => ExamEnrollmentStatus::Confirmed->value]);

        return $graph;
    }

    /**
     * @param  array{
     *   exam_enrollment_id:int,session_id:int,enrollment_id:int,
     *   year_id:int,student_id:int,subject_id:int
     * }  $graph
     */
    private function insertGrade(array $graph, int $schoolId, bool $isCurrent): void
    {
        DB::table(SchemaHelper::qualified('exams', 'student_grades'))->insert([
            'academic_year_id' => $graph['year_id'],
            'school_id' => $schoolId,
            'exam_enrollment_id' => $graph['exam_enrollment_id'],
            'exam_session_id' => $graph['session_id'],
            'enrollment_id' => $graph['enrollment_id'],
            'student_id' => $graph['student_id'],
            'subject_id' => $graph['subject_id'],
            'score' => 70,
            'max_score' => 100,
            'is_absent' => false,
            'status' => $isCurrent ? 2 : 5,
            'is_current' => $isCurrent,
            'correction_of_grade_id' => null,
            'entered_by' => null,
            'entered_at' => now(),
            'finalized_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array{
     *   exam_enrollment_id:int,session_id:int,enrollment_id:int
     * }  $graph
     */
    private function cmd(
        int $schoolId,
        array $graph,
        int $actorUserId,
        string $idempotencyKey,
    ): PresentExamEnrollmentCommand {
        return new PresentExamEnrollmentCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            examSessionId: $graph['session_id'],
            enrollmentId: $graph['enrollment_id'],
            actorUserId: $actorUserId,
            idempotencyKey: $idempotencyKey,
        );
    }

    private function bindSchool(int $schoolId): void
    {
        $this->app->make(SchoolContext::class)->set($schoolId);
    }

    private function gradeCount(int $schoolId): int
    {
        return (int) DB::table(SchemaHelper::qualified('exams', 'student_grades'))
            ->where('school_id', $schoolId)
            ->count();
    }

    private function attendanceCount(int $schoolId): int
    {
        try {
            return (int) DB::table(SchemaHelper::qualified('attendance', 'records'))
                ->where('school_id', $schoolId)
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
