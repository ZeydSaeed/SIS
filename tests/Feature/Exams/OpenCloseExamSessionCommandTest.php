<?php

namespace Tests\Feature\Exams;

use App\Application\Exams\Commands\CloseExamSessionCommand;
use App\Application\Exams\Commands\CloseExamSessionHandler;
use App\Application\Exams\Commands\OpenExamSessionCommand;
use App\Application\Exams\Commands\OpenExamSessionHandler;
use App\Database\SchemaHelper;
use App\Domain\Exams\Events\ExamSessionClosed;
use App\Domain\Exams\Events\ExamSessionOpened;
use App\Domain\Exams\Exceptions\ExamAuthorityDeniedException;
use App\Domain\Exams\Exceptions\ExamUpdateForbiddenException;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Domain\Exams\ValueObjects\ExamStatus;
use App\Security\Context\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;
use Tests\TestCase;

final class OpenCloseExamSessionCommandTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function grades_manager_opens_scheduled_session_with_outbox_and_idempotency(): void
    {
        $schoolId = $this->createSchool('SCH-72-O1', 'Open Session School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'O1');
        $handler = $this->app->make(OpenExamSessionHandler::class);

        $gradesBefore = $this->gradeCount($schoolId);
        $attendanceBefore = $this->attendanceCount($schoolId);

        $result = $handler->handle(new OpenExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'open-session-1',
        ));

        $this->assertTrue($result->success);
        $this->assertSame(ExamSessionStatus::InProgress->value, $result->status);
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_sessions'), [
            'id' => $graph['session_id'],
            'status' => ExamSessionStatus::InProgress->value,
        ]);

        $outboxPayload = json_decode(
            (string) DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamSessionOpened::class)
                ->orderByDesc('id')
                ->value('payload'),
            true,
        );
        $this->assertSame('exam_session_open', $outboxPayload['cause'] ?? null);
        $this->assertTrue(
            DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))
                ->where('key', 'open-session-1')
                ->where('command_name', OpenExamSessionHandler::COMMAND_NAME)
                ->exists()
        );
        $this->assertSame($gradesBefore, $this->gradeCount($schoolId));
        $this->assertSame($attendanceBefore, $this->attendanceCount($schoolId));

        $replay = $handler->handle(new OpenExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'open-session-1',
        ));
        $this->assertTrue($replay->fromIdempotencyCache);
    }

    #[Test]
    public function missing_open_authorization_fails_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-O2', 'Open Deny School');
        $teacher = $this->actingAsGradesTeacher(schoolId: $schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'O2');

        $this->expectException(ExamAuthorityDeniedException::class);
        $this->app->make(OpenExamSessionHandler::class)->handle(new OpenExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $teacher->id,
            idempotencyKey: 'open-denied',
        ));
    }

    #[Test]
    public function cancelled_session_cannot_open(): void
    {
        $schoolId = $this->createSchool('SCH-72-O3', 'Open Cancelled Session');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'O3');

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::Cancelled->value]);

        $this->expectException(ExamUpdateForbiddenException::class);
        $this->app->make(OpenExamSessionHandler::class)->handle(new OpenExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'open-cancelled-session',
        ));
    }

    #[Test]
    public function cancelled_parent_exam_cannot_open(): void
    {
        $schoolId = $this->createSchool('SCH-72-O4', 'Open Cancelled Exam');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'O4');

        DB::table(SchemaHelper::qualified('exams', 'exams'))
            ->where('id', $graph['exam_id'])
            ->update(['status' => ExamStatus::Cancelled->value]);

        $this->expectException(ExamValidationException::class);
        $this->app->make(OpenExamSessionHandler::class)->handle(new OpenExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'open-cancelled-exam',
        ));
    }

    #[Test]
    public function completed_session_cannot_reopen(): void
    {
        $schoolId = $this->createSchool('SCH-72-O5', 'Open Completed Session');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'O5');

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::Completed->value]);

        $this->expectException(ExamUpdateForbiddenException::class);
        $this->app->make(OpenExamSessionHandler::class)->handle(new OpenExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'open-completed',
        ));
    }

    #[Test]
    public function open_cross_school_fails_closed(): void
    {
        $schoolA = $this->createSchool('SCH-72-OA', 'Open School A');
        $schoolB = $this->createSchool('SCH-72-OB', 'Open School B');
        $user = $this->actingAsGradesManagerForSchool($schoolA);
        $this->bindSchool($schoolA);
        $graphB = $this->seedExamGradeGraph($schoolB, suffix: 'OB');

        $this->expectException(ExamValidationException::class);
        $this->app->make(OpenExamSessionHandler::class)->handle(new OpenExamSessionCommand(
            schoolId: $schoolA,
            examSessionId: $graphB['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'open-cross',
        ));
    }

    #[Test]
    public function grades_manager_closes_in_progress_session_with_outbox_and_idempotency(): void
    {
        $schoolId = $this->createSchool('SCH-72-C1', 'Close Session School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'C1');

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::InProgress->value]);

        $handler = $this->app->make(CloseExamSessionHandler::class);
        $gradesBefore = $this->gradeCount($schoolId);
        $attendanceBefore = $this->attendanceCount($schoolId);

        $result = $handler->handle(new CloseExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'close-session-1',
        ));

        $this->assertTrue($result->success);
        $this->assertSame(ExamSessionStatus::Completed->value, $result->status);
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_sessions'), [
            'id' => $graph['session_id'],
            'status' => ExamSessionStatus::Completed->value,
        ]);

        $outboxPayload = json_decode(
            (string) DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamSessionClosed::class)
                ->orderByDesc('id')
                ->value('payload'),
            true,
        );
        $this->assertSame('exam_session_close', $outboxPayload['cause'] ?? null);
        $this->assertFalse(class_exists(\App\Application\Exams\Commands\CompleteExamHandler::class));
        $this->assertSame($gradesBefore, $this->gradeCount($schoolId));
        $this->assertSame($attendanceBefore, $this->attendanceCount($schoolId));

        $replay = $handler->handle(new CloseExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'close-session-1',
        ));
        $this->assertTrue($replay->fromIdempotencyCache);
    }

    #[Test]
    public function missing_close_authorization_fails_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-C2', 'Close Deny School');
        $teacher = $this->actingAsGradesTeacher(schoolId: $schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'C2');

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::InProgress->value]);

        $this->expectException(ExamAuthorityDeniedException::class);
        $this->app->make(CloseExamSessionHandler::class)->handle(new CloseExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $teacher->id,
            idempotencyKey: 'close-denied',
        ));
    }

    #[Test]
    public function scheduled_to_completed_is_forbidden(): void
    {
        $schoolId = $this->createSchool('SCH-72-C3', 'Close Skip School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'C3');

        $this->expectException(ExamUpdateForbiddenException::class);
        $this->app->make(CloseExamSessionHandler::class)->handle(new CloseExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'close-scheduled',
        ));
    }

    #[Test]
    public function cancelled_session_cannot_close(): void
    {
        $schoolId = $this->createSchool('SCH-72-C4', 'Close Cancelled Session');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'C4');

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::Cancelled->value]);

        $this->expectException(ExamUpdateForbiddenException::class);
        $this->app->make(CloseExamSessionHandler::class)->handle(new CloseExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'close-cancelled-session',
        ));
    }

    #[Test]
    public function cancelled_parent_exam_cannot_close(): void
    {
        $schoolId = $this->createSchool('SCH-72-C5', 'Close Cancelled Exam');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'C5');

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::InProgress->value]);
        DB::table(SchemaHelper::qualified('exams', 'exams'))
            ->where('id', $graph['exam_id'])
            ->update(['status' => ExamStatus::Cancelled->value]);

        $this->expectException(ExamValidationException::class);
        $this->app->make(CloseExamSessionHandler::class)->handle(new CloseExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'close-cancelled-exam',
        ));
    }

    #[Test]
    public function close_cross_school_fails_closed(): void
    {
        $schoolA = $this->createSchool('SCH-72-CA', 'Close School A');
        $schoolB = $this->createSchool('SCH-72-CB', 'Close School B');
        $user = $this->actingAsGradesManagerForSchool($schoolA);
        $this->bindSchool($schoolA);
        $graphB = $this->seedExamGradeGraph($schoolB, suffix: 'CB');

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graphB['session_id'])
            ->update(['status' => ExamSessionStatus::InProgress->value]);

        $this->expectException(ExamValidationException::class);
        $this->app->make(CloseExamSessionHandler::class)->handle(new CloseExamSessionCommand(
            schoolId: $schoolA,
            examSessionId: $graphB['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'close-cross',
        ));
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
