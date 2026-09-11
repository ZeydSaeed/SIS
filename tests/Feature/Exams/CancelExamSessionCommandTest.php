<?php

namespace Tests\Feature\Exams;

use App\Application\Exams\Commands\CancelExamSessionCommand;
use App\Application\Exams\Commands\CancelExamSessionHandler;
use App\Database\SchemaHelper;
use App\Domain\Exams\Events\ExamEnrollmentCancelled;
use App\Domain\Exams\Events\ExamSessionCancelled;
use App\Domain\Exams\Exceptions\ExamAuthorityDeniedException;
use App\Domain\Exams\Exceptions\ExamCancelBlockedException;
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

final class CancelExamSessionCommandTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function grades_manager_cancels_scheduled_session_withdraws_seats_atomically(): void
    {
        $schoolId = $this->createSchool('SCH-72-X1', 'Cancel Session School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'X1');

        $gradesBefore = $this->gradeCount($schoolId);
        $attendanceBefore = $this->attendanceCount($schoolId);

        $result = $this->app->make(CancelExamSessionHandler::class)->handle(new CancelExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'cancel-session-1',
        ));

        $this->assertTrue($result->success);
        $this->assertSame(ExamSessionStatus::Cancelled->value, $result->status);
        $this->assertContains($graph['exam_enrollment_id'], $result->withdrawnEnrollmentIds);

        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_sessions'), [
            'id' => $graph['session_id'],
            'status' => ExamSessionStatus::Cancelled->value,
            'exam_id' => $graph['exam_id'],
            'subject_id' => $graph['subject_id'],
            'school_id' => $schoolId,
        ]);

        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_enrollments'), [
            'id' => $graph['exam_enrollment_id'],
            'status' => ExamEnrollmentStatus::Withdrawn->value,
        ]);

        $sessionPayload = json_decode(
            (string) DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamSessionCancelled::class)
                ->orderByDesc('id')
                ->value('payload'),
            true,
        );
        $this->assertSame('exam_session_cancel', $sessionPayload['cause'] ?? null);

        $enrollmentPayload = json_decode(
            (string) DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamEnrollmentCancelled::class)
                ->orderByDesc('id')
                ->value('payload'),
            true,
        );
        $this->assertSame('exam_session_cancel', $enrollmentPayload['cause'] ?? null);

        $this->assertTrue(
            DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))
                ->where('key', 'cancel-session-1')
                ->where('command_name', CancelExamSessionHandler::COMMAND_NAME)
                ->exists()
        );

        $this->assertSame($gradesBefore, $this->gradeCount($schoolId));
        $this->assertSame($attendanceBefore, $this->attendanceCount($schoolId));
    }

    #[Test]
    public function cancels_in_progress_session(): void
    {
        $schoolId = $this->createSchool('SCH-72-X2', 'Cancel InProgress School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'X2');

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::InProgress->value]);

        $result = $this->app->make(CancelExamSessionHandler::class)->handle(new CancelExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'cancel-in-progress',
        ));

        $this->assertSame(ExamSessionStatus::Cancelled->value, $result->status);
    }

    #[Test]
    public function missing_authorization_fails_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-X3', 'Cancel Deny School');
        $teacher = $this->actingAsGradesTeacher(schoolId: $schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'X3');

        $this->expectException(ExamAuthorityDeniedException::class);
        $this->app->make(CancelExamSessionHandler::class)->handle(new CancelExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $teacher->id,
            idempotencyKey: 'cancel-denied',
        ));
    }

    #[Test]
    public function cross_school_cancel_fails_closed(): void
    {
        $schoolA = $this->createSchool('SCH-72-XA', 'Cancel School A');
        $schoolB = $this->createSchool('SCH-72-XB', 'Cancel School B');
        $user = $this->actingAsGradesManagerForSchool($schoolA);
        $this->bindSchool($schoolA);
        $graphB = $this->seedExamGradeGraph($schoolB, suffix: 'XB');

        $this->expectException(ExamValidationException::class);
        $this->app->make(CancelExamSessionHandler::class)->handle(new CancelExamSessionCommand(
            schoolId: $schoolA,
            examSessionId: $graphB['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'cancel-cross',
        ));
    }

    #[Test]
    public function completed_session_cannot_cancel(): void
    {
        $schoolId = $this->createSchool('SCH-72-X4', 'Cancel Completed School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'X4');

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::Completed->value]);

        $this->expectException(ExamCancelBlockedException::class);
        $this->app->make(CancelExamSessionHandler::class)->handle(new CancelExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'cancel-completed',
        ));
    }

    #[Test]
    public function already_cancelled_is_idempotent_noop_without_duplicate_outbox(): void
    {
        $schoolId = $this->createSchool('SCH-72-X5', 'Cancel Noop School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'X5');
        $handler = $this->app->make(CancelExamSessionHandler::class);

        $handler->handle(new CancelExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'cancel-first',
        ));

        $outboxBefore = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
            ->where('event_type', ExamSessionCancelled::class)
            ->count();

        $noop = $handler->handle(new CancelExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'cancel-noop-second-key',
        ));

        $this->assertTrue($noop->success);
        $this->assertSame(ExamSessionStatus::Cancelled->value, $noop->status);
        $this->assertSame([], $noop->withdrawnEnrollmentIds);
        $this->assertSame(
            $outboxBefore,
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamSessionCancelled::class)
                ->count()
        );
    }

    #[Test]
    public function current_grade_blocks_cancel_without_mutating_grades_or_seats(): void
    {
        $schoolId = $this->createSchool('SCH-72-X6', 'Cancel Grade Guard School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'X6');

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

        $gradesBefore = $this->gradeCount($schoolId);

        try {
            $this->app->make(CancelExamSessionHandler::class)->handle(new CancelExamSessionCommand(
                schoolId: $schoolId,
                examSessionId: $graph['session_id'],
                actorUserId: (int) $user->id,
                idempotencyKey: 'cancel-current-grade',
            ));
            $this->fail('Expected ExamCancelBlockedException');
        } catch (ExamCancelBlockedException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_sessions'), [
            'id' => $graph['session_id'],
            'status' => ExamSessionStatus::Scheduled->value,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_enrollments'), [
            'id' => $graph['exam_enrollment_id'],
            'status' => ExamEnrollmentStatus::Registered->value,
        ]);
        $this->assertSame($gradesBefore, $this->gradeCount($schoolId));
        $this->assertSame(
            0,
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamSessionCancelled::class)
                ->where('payload', 'like', '%"exam_session_id":'.$graph['session_id'].'%')
                ->count()
        );
    }

    #[Test]
    public function idempotency_replay_and_payload_conflict(): void
    {
        $schoolId = $this->createSchool('SCH-72-X7', 'Cancel Idemp School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'X7');
        $handler = $this->app->make(CancelExamSessionHandler::class);

        $first = $handler->handle(new CancelExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'cancel-idemp',
        ));

        $replay = $handler->handle(new CancelExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'cancel-idemp',
        ));
        $this->assertTrue($replay->fromIdempotencyCache);
        $this->assertSame($first->examSessionId, $replay->examSessionId);

        $other = $this->seedExamGradeGraph($schoolId, suffix: 'X7b');
        $this->expectException(IdempotencyPayloadConflictException::class);
        $handler->handle(new CancelExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $other['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'cancel-idemp',
        ));
    }

    #[Test]
    public function exam_session_cancel_permission_is_not_registered(): void
    {
        $this->assertArrayNotHasKey('exam.session.cancel', config('security.permissions'));
        $this->assertNotContains('exam.session.cancel', config('security.roles.grades_manager'));
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
