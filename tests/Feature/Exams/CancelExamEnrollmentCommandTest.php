<?php

namespace Tests\Feature\Exams;

use App\Application\Exams\Commands\CancelExamEnrollmentCommand;
use App\Application\Exams\Commands\CancelExamEnrollmentHandler;
use App\Application\Exams\Commands\CancelExamSessionCommand;
use App\Application\Exams\Commands\CancelExamSessionHandler;
use App\Database\SchemaHelper;
use App\Domain\Exams\Events\ExamEnrollmentCancelled;
use App\Domain\Exams\Exceptions\ExamAuthorityDeniedException;
use App\Domain\Exams\Exceptions\ExamCancelBlockedException;
use App\Domain\Exams\Exceptions\ExamUpdateForbiddenException;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Exceptions\IdempotencyPayloadConflictException;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Security\Context\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;
use Tests\TestCase;

final class CancelExamEnrollmentCommandTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function grades_manager_withdraws_registered_with_outbox_and_idempotency(): void
    {
        $schoolId = $this->createSchool('SCH-72-U08-1', 'Cancel Enroll School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U081');
        $handler = $this->app->make(CancelExamEnrollmentHandler::class);

        $gradesBefore = $this->gradeCount($schoolId);
        $attendanceBefore = $this->attendanceCount($schoolId);

        $result = $handler->handle($this->cmd($schoolId, $graph, (int) $user->id, 'u08-cancel-1'));

        $this->assertTrue($result->success);
        $this->assertFalse($result->noop);
        $this->assertSame(ExamEnrollmentStatus::Withdrawn->value, $result->status);
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_enrollments'), [
            'id' => $graph['exam_enrollment_id'],
            'status' => ExamEnrollmentStatus::Withdrawn->value,
            'exam_session_id' => $graph['session_id'],
            'enrollment_id' => $graph['enrollment_id'],
            'school_id' => $schoolId,
        ]);

        $payload = json_decode(
            (string) DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamEnrollmentCancelled::class)
                ->orderByDesc('id')
                ->value('payload'),
            true,
        );
        $this->assertSame('exam_enrollment_cancel', $payload['cause'] ?? null);
        $this->assertSame(ExamEnrollmentStatus::Registered->value, $payload['previous_status'] ?? null);

        $this->assertSame($gradesBefore, $this->gradeCount($schoolId));
        $this->assertSame($attendanceBefore, $this->attendanceCount($schoolId));

        $replay = $handler->handle($this->cmd($schoolId, $graph, (int) $user->id, 'u08-cancel-1'));
        $this->assertTrue($replay->fromIdempotencyCache);
        $this->assertSame(
            1,
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamEnrollmentCancelled::class)
                ->where('payload', 'like', '%"exam_enrollment_id":'.$graph['exam_enrollment_id'].'%')
                ->where('payload', 'like', '%exam_enrollment_cancel%')
                ->count()
        );
    }

    #[Test]
    public function confirmed_and_present_can_be_withdrawn(): void
    {
        $schoolId = $this->createSchool('SCH-72-U08-2', 'Active Withdraw School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $handler = $this->app->make(CancelExamEnrollmentHandler::class);

        foreach ([
            [ExamEnrollmentStatus::Confirmed, 'conf'],
            [ExamEnrollmentStatus::Present, 'pres'],
        ] as [$from, $suffix]) {
            $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U082'.$suffix);
            DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
                ->where('id', $graph['exam_enrollment_id'])
                ->update(['status' => $from->value]);

            $result = $handler->handle($this->cmd($schoolId, $graph, (int) $user->id, 'u08-'.$suffix));
            $this->assertSame(ExamEnrollmentStatus::Withdrawn->value, $result->status);
        }
    }

    #[Test]
    public function absent_to_withdrawn_is_forbidden(): void
    {
        $schoolId = $this->createSchool('SCH-72-U08-3', 'Absent Forbid School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U083');
        DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
            ->where('id', $graph['exam_enrollment_id'])
            ->update(['status' => ExamEnrollmentStatus::Absent->value]);

        $this->expectException(ExamUpdateForbiddenException::class);
        $this->app->make(CancelExamEnrollmentHandler::class)->handle(
            $this->cmd($schoolId, $graph, (int) $user->id, 'u08-absent'),
        );
    }

    #[Test]
    public function already_withdrawn_is_idempotent_noop_without_second_event(): void
    {
        $schoolId = $this->createSchool('SCH-72-U08-4', 'Noop School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U084');
        $handler = $this->app->make(CancelExamEnrollmentHandler::class);

        $handler->handle($this->cmd($schoolId, $graph, (int) $user->id, 'u08-first'));
        $eventsAfterFirst = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
            ->where('event_type', ExamEnrollmentCancelled::class)
            ->where('payload', 'like', '%exam_enrollment_cancel%')
            ->count();

        $noop = $handler->handle($this->cmd($schoolId, $graph, (int) $user->id, 'u08-second-key'));
        $this->assertTrue($noop->success);
        $this->assertTrue($noop->noop);
        $this->assertSame(ExamEnrollmentStatus::Withdrawn->value, $noop->status);
        $this->assertSame(
            $eventsAfterFirst,
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamEnrollmentCancelled::class)
                ->where('payload', 'like', '%exam_enrollment_cancel%')
                ->count()
        );
    }

    #[Test]
    public function missing_authorization_fails_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-U08-5', 'Deny School');
        $teacher = $this->actingAsGradesTeacher(schoolId: $schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U085');

        $this->expectException(ExamAuthorityDeniedException::class);
        $this->app->make(CancelExamEnrollmentHandler::class)->handle(
            $this->cmd($schoolId, $graph, (int) $teacher->id, 'u08-denied'),
        );
    }

    #[Test]
    public function exam_session_cancel_permission_remains_absent(): void
    {
        $this->assertArrayNotHasKey('exam.session.cancel', config('security.permissions'));
        $this->assertNotContains('exam.session.cancel', config('security.roles.grades_manager'));
    }

    #[Test]
    public function current_grade_blocks_cancel_without_mutation_or_event(): void
    {
        $schoolId = $this->createSchool('SCH-72-U08-6', 'Grade Block School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U086');

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
            'status' => 2,
            'is_current' => true,
            'correction_of_grade_id' => null,
            'entered_by' => null,
            'entered_at' => now(),
            'finalized_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $outboxBefore = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count();

        try {
            $this->app->make(CancelExamEnrollmentHandler::class)->handle(
                $this->cmd($schoolId, $graph, (int) $user->id, 'u08-grade-block'),
            );
            $this->fail('Expected CURRENT grade block');
        } catch (ExamCancelBlockedException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_enrollments'), [
            'id' => $graph['exam_enrollment_id'],
            'status' => ExamEnrollmentStatus::Registered->value,
        ]);
        $this->assertSame($outboxBefore, DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count());
    }

    #[Test]
    public function historical_grade_does_not_block_cancel(): void
    {
        $schoolId = $this->createSchool('SCH-72-U08-7', 'Hist Grade School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U087');

        DB::table(SchemaHelper::qualified('exams', 'student_grades'))->insert([
            'academic_year_id' => $graph['year_id'],
            'school_id' => $schoolId,
            'exam_enrollment_id' => $graph['exam_enrollment_id'],
            'exam_session_id' => $graph['session_id'],
            'enrollment_id' => $graph['enrollment_id'],
            'student_id' => $graph['student_id'],
            'subject_id' => $graph['subject_id'],
            'score' => 40,
            'max_score' => 100,
            'is_absent' => false,
            'status' => 5,
            'is_current' => false,
            'correction_of_grade_id' => null,
            'entered_by' => null,
            'entered_at' => now(),
            'finalized_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->app->make(CancelExamEnrollmentHandler::class)->handle(
            $this->cmd($schoolId, $graph, (int) $user->id, 'u08-hist-ok'),
        );
        $this->assertSame(ExamEnrollmentStatus::Withdrawn->value, $result->status);
    }

    #[Test]
    public function cancelled_session_allows_active_withdraw_and_rejects_absent(): void
    {
        $schoolId = $this->createSchool('SCH-72-U08-8', 'Cancelled Session School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $handler = $this->app->make(CancelExamEnrollmentHandler::class);

        $active = $this->seedExamGradeGraph($schoolId, suffix: 'U088a');
        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $active['session_id'])
            ->update(['status' => ExamSessionStatus::Cancelled->value]);

        $ok = $handler->handle($this->cmd($schoolId, $active, (int) $user->id, 'u08-cancel-active'));
        $this->assertSame(ExamEnrollmentStatus::Withdrawn->value, $ok->status);

        $absent = $this->seedExamGradeGraph($schoolId, suffix: 'U088b');
        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $absent['session_id'])
            ->update(['status' => ExamSessionStatus::Cancelled->value]);
        DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
            ->where('id', $absent['exam_enrollment_id'])
            ->update(['status' => ExamEnrollmentStatus::Absent->value]);

        $this->expectException(ExamUpdateForbiddenException::class);
        $handler->handle($this->cmd($schoolId, $absent, (int) $user->id, 'u08-cancel-absent'));
    }

    #[Test]
    public function cancelled_session_withdrawn_is_noop(): void
    {
        $schoolId = $this->createSchool('SCH-72-U08-9', 'Cancelled Noop School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U089');

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::Cancelled->value]);
        DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
            ->where('id', $graph['exam_enrollment_id'])
            ->update(['status' => ExamEnrollmentStatus::Withdrawn->value]);

        $result = $this->app->make(CancelExamEnrollmentHandler::class)->handle(
            $this->cmd($schoolId, $graph, (int) $user->id, 'u08-cancel-noop'),
        );
        $this->assertTrue($result->noop);
    }

    #[Test]
    public function cross_school_fails_closed_without_mutation_or_outbox(): void
    {
        $schoolA = $this->createSchool('SCH-72-U08-A', 'Cancel School A');
        $schoolB = $this->createSchool('SCH-72-U08-B', 'Cancel School B');
        $user = $this->actingAsGradesManagerForSchool($schoolA);
        $this->bindSchool($schoolA);
        $graphB = $this->seedExamGradeGraph($schoolB, suffix: 'U08B');

        $outboxBefore = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count();
        $idempBefore = DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))->count();

        try {
            $this->app->make(CancelExamEnrollmentHandler::class)->handle(new CancelExamEnrollmentCommand(
                schoolId: $schoolA,
                examEnrollmentId: $graphB['exam_enrollment_id'],
                examSessionId: $graphB['session_id'],
                enrollmentId: $graphB['enrollment_id'],
                actorUserId: (int) $user->id,
                idempotencyKey: 'u08-cross',
            ));
            $this->fail('Expected cross-school rejection');
        } catch (ExamValidationException) {
            $this->assertTrue(true);
        }

        $this->assertSame($outboxBefore, DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count());
        $this->assertSame($idempBefore, DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))->count());
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_enrollments'), [
            'id' => $graphB['exam_enrollment_id'],
            'status' => ExamEnrollmentStatus::Registered->value,
        ]);
    }

    #[Test]
    public function idempotency_conflict_fails_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-U08-10', 'Idemp Conflict School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $first = $this->seedExamGradeGraph($schoolId, suffix: 'U0810a');
        $second = $this->seedExamGradeGraph($schoolId, suffix: 'U0810b');
        $handler = $this->app->make(CancelExamEnrollmentHandler::class);

        $handler->handle($this->cmd($schoolId, $first, (int) $user->id, 'u08-conflict'));

        $this->expectException(IdempotencyPayloadConflictException::class);
        $handler->handle($this->cmd($schoolId, $second, (int) $user->id, 'u08-conflict'));
    }

    #[Test]
    public function u05_first_then_u08_is_noop_without_u08_event(): void
    {
        $schoolId = $this->createSchool('SCH-72-U08-11', 'U05 First School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U0811');

        $this->app->make(CancelExamSessionHandler::class)->handle(new CancelExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u05-first',
        ));

        $u08EventsBefore = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
            ->where('event_type', ExamEnrollmentCancelled::class)
            ->where('payload', 'like', '%exam_enrollment_cancel%')
            ->count();

        $result = $this->app->make(CancelExamEnrollmentHandler::class)->handle(
            $this->cmd($schoolId, $graph, (int) $user->id, 'u08-after-u05'),
        );

        $this->assertTrue($result->noop);
        $this->assertSame(
            $u08EventsBefore,
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamEnrollmentCancelled::class)
                ->where('payload', 'like', '%exam_enrollment_cancel%')
                ->count()
        );
    }

    #[Test]
    public function u08_first_then_u05_does_not_reselect_withdrawn_seat(): void
    {
        $schoolId = $this->createSchool('SCH-72-U08-12', 'U08 First School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U0812');

        $this->app->make(CancelExamEnrollmentHandler::class)->handle(
            $this->cmd($schoolId, $graph, (int) $user->id, 'u08-before-u05'),
        );

        $sessionEventsBefore = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
            ->where('event_type', ExamEnrollmentCancelled::class)
            ->where('payload', 'like', '%"exam_enrollment_id":'.$graph['exam_enrollment_id'].'%')
            ->where('payload', 'like', '%exam_session_cancel%')
            ->count();

        $this->app->make(CancelExamSessionHandler::class)->handle(new CancelExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u05-after-u08',
        ));

        $this->assertSame(
            $sessionEventsBefore,
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamEnrollmentCancelled::class)
                ->where('payload', 'like', '%"exam_enrollment_id":'.$graph['exam_enrollment_id'].'%')
                ->where('payload', 'like', '%exam_session_cancel%')
                ->count()
        );
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_enrollments'), [
            'id' => $graph['exam_enrollment_id'],
            'status' => ExamEnrollmentStatus::Withdrawn->value,
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
    ): CancelExamEnrollmentCommand {
        return new CancelExamEnrollmentCommand(
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
