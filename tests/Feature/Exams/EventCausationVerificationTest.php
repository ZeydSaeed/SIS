<?php

namespace Tests\Feature\Exams;

use App\Application\Exams\Commands\CancelExamCommand;
use App\Application\Exams\Commands\CancelExamEnrollmentCommand;
use App\Application\Exams\Commands\CancelExamEnrollmentHandler;
use App\Application\Exams\Commands\CancelExamHandler;
use App\Application\Exams\Commands\CancelExamSessionCommand;
use App\Application\Exams\Commands\CancelExamSessionHandler;
use App\Database\SchemaHelper;
use App\Domain\Exams\Events\ExamEnrollmentCancelled;
use App\Domain\Exams\Events\ExamSessionCancelled;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;
use App\Security\Context\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;
use Tests\TestCase;

/**
 * Phase 7.2 Batch 6 U14 — HD-7.2-011 / DR-006 event causation verification.
 */
final class EventCausationVerificationTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function cancel_exam_cascade_session_event_has_exam_cancel_cause(): void
    {
        $schoolId = $this->createSchool('SCH-72-U14-A', 'U14 Cascade Session');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U14A');

        $this->app->make(CancelExamHandler::class)->handle(new CancelExamCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u14-cascade-session',
        ));

        $row = $this->latestOutboxRow(
            ExamSessionCancelled::class,
            '%"exam_session_id":'.$graph['session_id'].'%',
        );
        $payload = json_decode((string) $row->payload, true) ?: [];

        $this->assertSame('exam_cancel', $payload['cause'] ?? null);
        $this->assertIdempotencyCommandName('u14-cascade-session', CancelExamHandler::COMMAND_NAME);
        $this->assertEventIdentityDistinctFromIdempotency((int) $row->id, 'u14-cascade-session');
    }

    #[Test]
    public function cancel_exam_cascade_enrollment_event_has_exam_cancel_cause(): void
    {
        $schoolId = $this->createSchool('SCH-72-U14-B', 'U14 Cascade Enrollment');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U14B');

        $this->app->make(CancelExamHandler::class)->handle(new CancelExamCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u14-cascade-enrollment',
        ));

        $row = $this->latestOutboxRow(
            ExamEnrollmentCancelled::class,
            '%"exam_enrollment_id":'.$graph['exam_enrollment_id'].'%',
        );
        $payload = json_decode((string) $row->payload, true) ?: [];

        $this->assertSame('exam_cancel', $payload['cause'] ?? null);
        $this->assertIdempotencyCommandName('u14-cascade-enrollment', CancelExamHandler::COMMAND_NAME);
        $this->assertEventIdentityDistinctFromIdempotency((int) $row->id, 'u14-cascade-enrollment');
    }

    #[Test]
    public function direct_cancel_exam_session_uses_exam_session_cancel_cause(): void
    {
        $schoolId = $this->createSchool('SCH-72-U14-C', 'U14 Direct Session');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U14C');

        $outboxBefore = (int) DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count();

        $this->app->make(CancelExamSessionHandler::class)->handle(new CancelExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u14-direct-session',
        ));

        $row = $this->latestOutboxRow(
            ExamSessionCancelled::class,
            '%"exam_session_id":'.$graph['session_id'].'%',
        );
        $payload = json_decode((string) $row->payload, true) ?: [];

        $this->assertSame('exam_session_cancel', $payload['cause'] ?? null);
        $this->assertIdempotencyCommandName('u14-direct-session', CancelExamSessionHandler::COMMAND_NAME);
        $this->assertEventIdentityDistinctFromIdempotency((int) $row->id, 'u14-direct-session');

        // Session cancel + one seat withdraw event — no U14 verification side-effects.
        $this->assertSame(
            $outboxBefore + 2,
            (int) DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count()
        );
        $this->assertSame(
            1,
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamSessionCancelled::class)
                ->where('payload', 'like', '%"exam_session_id":'.$graph['session_id'].'%')
                ->count()
        );
        $this->assertSame(
            1,
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamEnrollmentCancelled::class)
                ->where('payload', 'like', '%"exam_enrollment_id":'.$graph['exam_enrollment_id'].'%')
                ->count()
        );

        $enrollmentPayload = $this->latestOutboxPayload(
            ExamEnrollmentCancelled::class,
            '%"exam_enrollment_id":'.$graph['exam_enrollment_id'].'%',
        );
        $this->assertSame('exam_session_cancel', $enrollmentPayload['cause'] ?? null);
    }

    #[Test]
    public function direct_cancel_exam_enrollment_uses_exam_enrollment_cancel_cause(): void
    {
        $schoolId = $this->createSchool('SCH-72-U14-D', 'U14 Direct Enrollment');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U14D');

        $this->assertSame(
            ExamEnrollmentStatus::Registered->value,
            (int) DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
                ->where('id', $graph['exam_enrollment_id'])
                ->value('status')
        );

        $this->app->make(CancelExamEnrollmentHandler::class)->handle(new CancelExamEnrollmentCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            examEnrollmentId: $graph['exam_enrollment_id'],
            enrollmentId: $graph['enrollment_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u14-direct-enrollment',
        ));

        $row = $this->latestOutboxRow(
            ExamEnrollmentCancelled::class,
            '%"exam_enrollment_id":'.$graph['exam_enrollment_id'].'%',
        );
        $payload = json_decode((string) $row->payload, true) ?: [];

        $this->assertSame('exam_enrollment_cancel', $payload['cause'] ?? null);
        $this->assertIdempotencyCommandName('u14-direct-enrollment', CancelExamEnrollmentHandler::COMMAND_NAME);
        $this->assertEventIdentityDistinctFromIdempotency((int) $row->id, 'u14-direct-enrollment');
        $this->assertSame(
            1,
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamEnrollmentCancelled::class)
                ->where('payload', 'like', '%"exam_enrollment_id":'.$graph['exam_enrollment_id'].'%')
                ->count()
        );
    }

    #[Test]
    public function cascade_cause_is_distinguishable_from_direct_causes(): void
    {
        $this->assertNotSame('exam_cancel', 'exam_session_cancel');
        $this->assertNotSame('exam_cancel', 'exam_enrollment_cancel');
        $this->assertNotSame('exam_session_cancel', 'exam_enrollment_cancel');

        $schoolId = $this->createSchool('SCH-72-U14-E', 'U14 Distinction');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);

        $cascadeGraph = $this->seedExamGradeGraph($schoolId, suffix: 'U14E1');
        $sessionGraph = $this->seedExamGradeGraph($schoolId, yearId: $cascadeGraph['year_id'], suffix: 'U14E2');
        $enrollmentGraph = $this->seedExamGradeGraph($schoolId, yearId: $cascadeGraph['year_id'], suffix: 'U14E3');

        $this->app->make(CancelExamHandler::class)->handle(new CancelExamCommand(
            schoolId: $schoolId,
            examId: $cascadeGraph['exam_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u14-dist-cascade',
        ));
        $this->app->make(CancelExamSessionHandler::class)->handle(new CancelExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $sessionGraph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u14-dist-session',
        ));
        $this->app->make(CancelExamEnrollmentHandler::class)->handle(new CancelExamEnrollmentCommand(
            schoolId: $schoolId,
            examSessionId: $enrollmentGraph['session_id'],
            examEnrollmentId: $enrollmentGraph['exam_enrollment_id'],
            enrollmentId: $enrollmentGraph['enrollment_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u14-dist-enrollment',
        ));

        $cascadeCause = $this->latestOutboxPayload(
            ExamSessionCancelled::class,
            '%"exam_session_id":'.$cascadeGraph['session_id'].'%',
        )['cause'] ?? null;
        $directSessionCause = $this->latestOutboxPayload(
            ExamSessionCancelled::class,
            '%"exam_session_id":'.$sessionGraph['session_id'].'%',
        )['cause'] ?? null;
        $directEnrollmentCause = $this->latestOutboxPayload(
            ExamEnrollmentCancelled::class,
            '%"exam_enrollment_id":'.$enrollmentGraph['exam_enrollment_id'].'%',
        )['cause'] ?? null;

        $this->assertSame('exam_cancel', $cascadeCause);
        $this->assertSame('exam_session_cancel', $directSessionCause);
        $this->assertSame('exam_enrollment_cancel', $directEnrollmentCause);
        $this->assertNotSame($cascadeCause, $directSessionCause);
        $this->assertNotSame($cascadeCause, $directEnrollmentCause);
    }

    #[Test]
    public function originating_command_name_present_on_idempotency_envelope(): void
    {
        $schoolId = $this->createSchool('SCH-72-U14-F', 'U14 Command Name');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U14F');

        $this->app->make(CancelExamHandler::class)->handle(new CancelExamCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u14-cmd-name',
        ));

        $this->assertTrue(
            DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))
                ->where('key', 'u14-cmd-name')
                ->where('command_name', CancelExamHandler::COMMAND_NAME)
                ->exists()
        );
        $this->assertSame('Exams.CancelExam', CancelExamHandler::COMMAND_NAME);
        $this->assertSame('Exams.CancelExamSession', CancelExamSessionHandler::COMMAND_NAME);
        $this->assertSame('Exams.CancelExamEnrollment', CancelExamEnrollmentHandler::COMMAND_NAME);
    }

    #[Test]
    public function verification_does_not_emit_extra_outbox_beyond_writer_mutation(): void
    {
        $schoolId = $this->createSchool('SCH-72-U14-G', 'U14 No Extra Events');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U14G');

        $outboxBefore = (int) DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count();
        $gradesBefore = $this->gradeCount($schoolId);
        $attendanceBefore = $this->attendanceCount($schoolId);

        $this->app->make(CancelExamEnrollmentHandler::class)->handle(new CancelExamEnrollmentCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            examEnrollmentId: $graph['exam_enrollment_id'],
            enrollmentId: $graph['enrollment_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u14-no-extra',
        ));

        $outboxAfter = (int) DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count();
        $this->assertSame($outboxBefore + 1, $outboxAfter);
        $this->assertSame($gradesBefore, $this->gradeCount($schoolId));
        $this->assertSame($attendanceBefore, $this->attendanceCount($schoolId));
    }

    private function bindSchool(int $schoolId): void
    {
        $this->app->make(SchoolContext::class)->set($schoolId);
    }

    private function assertIdempotencyCommandName(string $key, string $commandName): void
    {
        $this->assertTrue(
            DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))
                ->where('key', $key)
                ->where('command_name', $commandName)
                ->exists(),
            "Expected idempotency command_name {$commandName} for key {$key}"
        );
    }

    private function assertEventIdentityDistinctFromIdempotency(int $outboxId, string $idempotencyKey): void
    {
        $this->assertNotSame((string) $outboxId, $idempotencyKey);
        $this->assertFalse(
            DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))
                ->where('key', (string) $outboxId)
                ->exists(),
            'Outbox message id must not be used as idempotency key'
        );

        $correlationId = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
            ->where('id', $outboxId)
            ->value('correlation_id');
        if ($correlationId !== null && $correlationId !== '') {
            $this->assertNotSame((string) $correlationId, $idempotencyKey);
        }
    }

    /**
     * @return object{id:int|string,payload:string}
     */
    private function latestOutboxRow(string $eventType, string $like): object
    {
        $row = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
            ->where('event_type', $eventType)
            ->where('payload', 'like', $like)
            ->orderByDesc('id')
            ->first(['id', 'payload']);

        $this->assertNotNull($row);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function latestOutboxPayload(string $eventType, string $like): array
    {
        return json_decode((string) $this->latestOutboxRow($eventType, $like)->payload, true) ?: [];
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
