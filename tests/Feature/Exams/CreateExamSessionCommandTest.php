<?php

namespace Tests\Feature\Exams;

use App\Application\Exams\Commands\CreateExamSessionCommand;
use App\Application\Exams\Commands\CreateExamSessionHandler;
use App\Database\SchemaHelper;
use App\Domain\Exams\Events\ExamSessionCreated;
use App\Domain\Exams\Exceptions\ExamAuthorityDeniedException;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Exceptions\IdempotencyPayloadConflictException;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Domain\Exams\ValueObjects\ExamStatus;
use App\Security\Context\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;
use Tests\TestCase;

final class CreateExamSessionCommandTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function grades_manager_creates_session_with_outbox_and_idempotency(): void
    {
        $schoolId = $this->createSchool('SCH-72-S1', 'Session Create School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'S1');

        $handler = $this->app->make(CreateExamSessionHandler::class);
        $result = $handler->handle(new CreateExamSessionCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            subjectId: $graph['subject_id'],
            sessionDate: '2026-11-06',
            startTime: '10:00:00',
            endTime: '12:00:00',
            actorUserId: (int) $user->id,
            idempotencyKey: 'create-session-1',
        ));

        $this->assertTrue($result->success);
        $this->assertSame(ExamSessionStatus::Scheduled->value, $result->status);
        $this->assertNotNull($result->examSessionId);

        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_sessions'), [
            'id' => $result->examSessionId,
            'exam_id' => $graph['exam_id'],
            'school_id' => $schoolId,
            'subject_id' => $graph['subject_id'],
            'status' => ExamSessionStatus::Scheduled->value,
        ]);

        $this->assertTrue(
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamSessionCreated::class)
                ->exists()
        );

        $outboxPayload = json_decode(
            (string) DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamSessionCreated::class)
                ->orderByDesc('id')
                ->value('payload'),
            true,
        );
        $this->assertSame('exam_session_create', $outboxPayload['cause'] ?? null);

        $this->assertTrue(
            DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))
                ->where('key', 'create-session-1')
                ->where('command_name', CreateExamSessionHandler::COMMAND_NAME)
                ->exists()
        );

        $this->assertSame(
            0,
            (int) DB::table(SchemaHelper::qualified('exams', 'student_grades'))
                ->where('school_id', $schoolId)
                ->where('created_at', '>=', now()->subMinute())
                ->count()
        );

        $replay = $handler->handle(new CreateExamSessionCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            subjectId: $graph['subject_id'],
            sessionDate: '2026-11-06',
            startTime: '10:00:00',
            endTime: '12:00:00',
            actorUserId: (int) $user->id,
            idempotencyKey: 'create-session-1',
        ));

        $this->assertTrue($replay->fromIdempotencyCache);
        $this->assertSame($result->examSessionId, $replay->examSessionId);
        $this->assertSame(
            2,
            DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
                ->where('exam_id', $graph['exam_id'])
                ->where('subject_id', $graph['subject_id'])
                ->count()
        );
    }

    #[Test]
    public function multiple_sessions_same_exam_subject_allowed(): void
    {
        $schoolId = $this->createSchool('SCH-72-S2', 'Multi Session School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'S2');
        $handler = $this->app->make(CreateExamSessionHandler::class);

        $first = $handler->handle(new CreateExamSessionCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            subjectId: $graph['subject_id'],
            sessionDate: '2026-11-07',
            startTime: '08:00:00',
            endTime: '09:00:00',
            actorUserId: (int) $user->id,
            idempotencyKey: 'multi-a',
        ));
        $second = $handler->handle(new CreateExamSessionCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            subjectId: $graph['subject_id'],
            sessionDate: '2026-11-08',
            startTime: '08:00:00',
            endTime: '09:00:00',
            actorUserId: (int) $user->id,
            idempotencyKey: 'multi-b',
        ));

        $this->assertNotSame($first->examSessionId, $second->examSessionId);
        $this->assertSame(
            3,
            DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
                ->where('exam_id', $graph['exam_id'])
                ->where('subject_id', $graph['subject_id'])
                ->count()
        );
    }

    #[Test]
    public function missing_authorization_fails_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-S3', 'Session Deny School');
        $teacher = $this->actingAsGradesTeacher(schoolId: $schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'S3');

        $this->expectException(ExamAuthorityDeniedException::class);
        $this->app->make(CreateExamSessionHandler::class)->handle(new CreateExamSessionCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            subjectId: $graph['subject_id'],
            sessionDate: '2026-11-06',
            startTime: '10:00:00',
            endTime: '12:00:00',
            actorUserId: (int) $teacher->id,
            idempotencyKey: 'denied-session',
        ));
    }

    #[Test]
    public function cross_school_exam_reference_fails_closed(): void
    {
        $schoolA = $this->createSchool('SCH-72-SA', 'Session School A');
        $schoolB = $this->createSchool('SCH-72-SB', 'Session School B');
        $user = $this->actingAsGradesManagerForSchool($schoolA);
        $this->bindSchool($schoolA);
        $graphB = $this->seedExamGradeGraph($schoolB, suffix: 'SB');

        $this->expectException(ExamValidationException::class);
        $this->app->make(CreateExamSessionHandler::class)->handle(new CreateExamSessionCommand(
            schoolId: $schoolA,
            examId: $graphB['exam_id'],
            subjectId: $graphB['subject_id'],
            sessionDate: '2026-11-06',
            startTime: '10:00:00',
            endTime: '12:00:00',
            actorUserId: (int) $user->id,
            idempotencyKey: 'cross-exam',
        ));
    }

    #[Test]
    public function room_from_other_school_fails_closed(): void
    {
        $schoolA = $this->createSchool('SCH-72-RA', 'Room School A');
        $schoolB = $this->createSchool('SCH-72-RB', 'Room School B');
        $user = $this->actingAsGradesManagerForSchool($schoolA);
        $this->bindSchool($schoolA);
        $graph = $this->seedExamGradeGraph($schoolA, suffix: 'RA');
        $foreignRoomId = $this->seedRoomForSchool($schoolB, 'RB');

        $this->expectException(ExamValidationException::class);
        $this->app->make(CreateExamSessionHandler::class)->handle(new CreateExamSessionCommand(
            schoolId: $schoolA,
            examId: $graph['exam_id'],
            subjectId: $graph['subject_id'],
            sessionDate: '2026-11-06',
            startTime: '10:00:00',
            endTime: '12:00:00',
            actorUserId: (int) $user->id,
            idempotencyKey: 'bad-room',
            roomId: $foreignRoomId,
        ));
    }

    #[Test]
    public function cancelled_parent_exam_fails_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-SC', 'Cancelled Parent School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'SC');

        DB::table(SchemaHelper::qualified('exams', 'exams'))
            ->where('id', $graph['exam_id'])
            ->update(['status' => ExamStatus::Cancelled->value]);

        $this->expectException(ExamValidationException::class);
        $this->app->make(CreateExamSessionHandler::class)->handle(new CreateExamSessionCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            subjectId: $graph['subject_id'],
            sessionDate: '2026-11-06',
            startTime: '10:00:00',
            endTime: '12:00:00',
            actorUserId: (int) $user->id,
            idempotencyKey: 'cancelled-parent',
        ));
    }

    #[Test]
    public function conflicting_idempotency_payload_fails_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-SI', 'Idemp School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'SI');
        $handler = $this->app->make(CreateExamSessionHandler::class);

        $handler->handle(new CreateExamSessionCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            subjectId: $graph['subject_id'],
            sessionDate: '2026-11-06',
            startTime: '10:00:00',
            endTime: '12:00:00',
            actorUserId: (int) $user->id,
            idempotencyKey: 'session-conflict',
        ));

        $this->expectException(IdempotencyPayloadConflictException::class);
        $handler->handle(new CreateExamSessionCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            subjectId: $graph['subject_id'],
            sessionDate: '2026-11-07',
            startTime: '10:00:00',
            endTime: '12:00:00',
            actorUserId: (int) $user->id,
            idempotencyKey: 'session-conflict',
        ));
    }

    private function bindSchool(int $schoolId): void
    {
        $this->app->make(SchoolContext::class)->set($schoolId);
    }

    private function seedRoomForSchool(int $schoolId, string $suffix): int
    {
        $branchId = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
            'school_id' => $schoolId,
            'code' => 'BR-'.$suffix.substr(uniqid(), -4),
            'name' => 'Branch '.$suffix,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table(SchemaHelper::qualified('organization', 'rooms'))->insertGetId([
            'branch_id' => $branchId,
            'code' => 'RM-'.$suffix.substr(uniqid(), -4),
            'name' => 'Room '.$suffix,
            'capacity' => 30,
            'room_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
