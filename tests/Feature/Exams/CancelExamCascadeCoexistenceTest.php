<?php

namespace Tests\Feature\Exams;

use App\Application\Exams\Commands\CancelExamCommand;
use App\Application\Exams\Commands\CancelExamEnrollmentCommand;
use App\Application\Exams\Commands\CancelExamEnrollmentHandler;
use App\Application\Exams\Commands\CancelExamHandler;
use App\Application\Exams\Commands\CancelExamSessionCommand;
use App\Application\Exams\Commands\CancelExamSessionHandler;
use App\Application\Exams\Commands\PresentExamEnrollmentCommand;
use App\Application\Exams\Commands\PresentExamEnrollmentHandler;
use App\Database\SchemaHelper;
use App\Domain\Exams\Events\ExamCancelled;
use App\Domain\Exams\Events\ExamEnrollmentCancelled;
use App\Domain\Exams\Events\ExamSessionCancelled;
use App\Domain\Exams\Exceptions\ExamAuthorityDeniedException;
use App\Domain\Exams\Exceptions\IdempotencyPayloadConflictException;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Domain\Exams\ValueObjects\ExamStatus;
use App\Security\Context\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;
use Tests\TestCase;

/**
 * U10 — CancelExam cascade coexistence (HD-7.2-002D / Gate 7.2-U10).
 */
final class CancelExamCascadeCoexistenceTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function cancel_exam_happy_path_cascades_with_exam_cancel_cause_and_no_grade_mutation(): void
    {
        $schoolId = $this->createSchool('SCH-72-U10-1', 'U10 Happy School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U101');

        $gradesBefore = $this->gradeCount($schoolId);
        $attendanceBefore = $this->attendanceCount($schoolId);

        $result = $this->app->make(CancelExamHandler::class)->handle(new CancelExamCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u10-happy',
        ));

        $this->assertSame(ExamStatus::Cancelled->value, $result->status);
        $this->assertContains($graph['session_id'], $result->cancelledSessionIds);
        $this->assertContains($graph['exam_enrollment_id'], $result->withdrawnEnrollmentIds);

        $sessionPayload = $this->latestOutboxPayload(
            ExamSessionCancelled::class,
            '%"exam_session_id":'.$graph['session_id'].'%',
        );
        $this->assertSame('exam_cancel', $sessionPayload['cause'] ?? null);

        $enrollmentPayload = $this->latestOutboxPayload(
            ExamEnrollmentCancelled::class,
            '%"exam_enrollment_id":'.$graph['exam_enrollment_id'].'%',
        );
        $this->assertSame('exam_cancel', $enrollmentPayload['cause'] ?? null);

        $this->assertTrue(
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamCancelled::class)
                ->where('payload', 'like', '%"exam_id":'.$graph['exam_id'].'%')
                ->exists()
        );

        $this->assertSame($gradesBefore, $this->gradeCount($schoolId));
        $this->assertSame($attendanceBefore, $this->attendanceCount($schoolId));

        $replay = $this->app->make(CancelExamHandler::class)->handle(new CancelExamCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u10-happy',
        ));
        $this->assertTrue($replay->fromIdempotencyCache);
        $this->assertSame(
            1,
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamSessionCancelled::class)
                ->where('payload', 'like', '%"exam_session_id":'.$graph['session_id'].'%')
                ->where('payload', 'like', '%exam_cancel%')
                ->count()
        );
    }

    #[Test]
    public function u05_then_cancel_exam_skips_already_cancelled_session_and_cascades_remainder(): void
    {
        $schoolId = $this->createSchool('SCH-72-U10-2', 'U10 U05 First School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $primary = $this->seedExamGradeGraph($schoolId, suffix: 'U102a');
        $secondary = $this->addSiblingSessionEnrollment($primary, $schoolId, 'U102b');

        $this->app->make(CancelExamSessionHandler::class)->handle(new CancelExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $primary['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u10-u05-first',
        ));

        $u05SessionEvents = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
            ->where('event_type', ExamSessionCancelled::class)
            ->where('payload', 'like', '%"exam_session_id":'.$primary['session_id'].'%')
            ->where('payload', 'like', '%exam_session_cancel%')
            ->count();
        $this->assertSame(1, $u05SessionEvents);

        $u05EnrollmentEvents = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
            ->where('event_type', ExamEnrollmentCancelled::class)
            ->where('payload', 'like', '%"exam_enrollment_id":'.$primary['exam_enrollment_id'].'%')
            ->where('payload', 'like', '%exam_session_cancel%')
            ->count();
        $this->assertSame(1, $u05EnrollmentEvents);

        $gradesBefore = $this->gradeCount($schoolId);

        $result = $this->app->make(CancelExamHandler::class)->handle(new CancelExamCommand(
            schoolId: $schoolId,
            examId: $primary['exam_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u10-after-u05',
        ));

        $this->assertSame(ExamStatus::Cancelled->value, $result->status);
        $this->assertNotContains($primary['session_id'], $result->cancelledSessionIds);
        $this->assertContains($secondary['session_id'], $result->cancelledSessionIds);
        $this->assertNotContains($primary['exam_enrollment_id'], $result->withdrawnEnrollmentIds);
        $this->assertContains($secondary['exam_enrollment_id'], $result->withdrawnEnrollmentIds);

        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_sessions'), [
            'id' => $primary['session_id'],
            'status' => ExamSessionStatus::Cancelled->value,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_sessions'), [
            'id' => $secondary['session_id'],
            'status' => ExamSessionStatus::Cancelled->value,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_enrollments'), [
            'id' => $primary['exam_enrollment_id'],
            'status' => ExamEnrollmentStatus::Withdrawn->value,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_enrollments'), [
            'id' => $secondary['exam_enrollment_id'],
            'status' => ExamEnrollmentStatus::Withdrawn->value,
        ]);

        $this->assertSame(
            $u05SessionEvents,
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamSessionCancelled::class)
                ->where('payload', 'like', '%"exam_session_id":'.$primary['session_id'].'%')
                ->where('payload', 'like', '%exam_session_cancel%')
                ->count()
        );
        $this->assertSame(
            0,
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamSessionCancelled::class)
                ->where('payload', 'like', '%"exam_session_id":'.$primary['session_id'].'%')
                ->where('payload', 'like', '%exam_cancel%')
                ->count()
        );

        $cascadeSession = $this->latestOutboxPayload(
            ExamSessionCancelled::class,
            '%"exam_session_id":'.$secondary['session_id'].'%',
        );
        $this->assertSame('exam_cancel', $cascadeSession['cause'] ?? null);

        $cascadeEnrollment = $this->latestOutboxPayload(
            ExamEnrollmentCancelled::class,
            '%"exam_enrollment_id":'.$secondary['exam_enrollment_id'].'%',
        );
        $this->assertSame('exam_cancel', $cascadeEnrollment['cause'] ?? null);

        $this->assertSame($gradesBefore, $this->gradeCount($schoolId));
    }

    #[Test]
    public function u08_then_cancel_exam_does_not_rewithdraw_seat_and_cascades_remainder(): void
    {
        $schoolId = $this->createSchool('SCH-72-U10-3', 'U10 U08 First School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $primary = $this->seedExamGradeGraph($schoolId, suffix: 'U103a');
        $secondary = $this->addSiblingSessionEnrollment($primary, $schoolId, 'U103b');

        $this->app->make(CancelExamEnrollmentHandler::class)->handle(new CancelExamEnrollmentCommand(
            schoolId: $schoolId,
            examEnrollmentId: $primary['exam_enrollment_id'],
            examSessionId: $primary['session_id'],
            enrollmentId: $primary['enrollment_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u10-u08-first',
        ));

        $u08Events = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
            ->where('event_type', ExamEnrollmentCancelled::class)
            ->where('payload', 'like', '%"exam_enrollment_id":'.$primary['exam_enrollment_id'].'%')
            ->where('payload', 'like', '%exam_enrollment_cancel%')
            ->count();
        $this->assertSame(1, $u08Events);

        $result = $this->app->make(CancelExamHandler::class)->handle(new CancelExamCommand(
            schoolId: $schoolId,
            examId: $primary['exam_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u10-after-u08',
        ));

        $this->assertContains($primary['session_id'], $result->cancelledSessionIds);
        $this->assertContains($secondary['session_id'], $result->cancelledSessionIds);
        $this->assertNotContains($primary['exam_enrollment_id'], $result->withdrawnEnrollmentIds);
        $this->assertContains($secondary['exam_enrollment_id'], $result->withdrawnEnrollmentIds);

        $this->assertSame(
            $u08Events,
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamEnrollmentCancelled::class)
                ->where('payload', 'like', '%"exam_enrollment_id":'.$primary['exam_enrollment_id'].'%')
                ->where('payload', 'like', '%exam_enrollment_cancel%')
                ->count()
        );
        $this->assertSame(
            0,
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamEnrollmentCancelled::class)
                ->where('payload', 'like', '%"exam_enrollment_id":'.$primary['exam_enrollment_id'].'%')
                ->where('payload', 'like', '%exam_cancel%')
                ->count()
        );
    }

    #[Test]
    public function unauthorized_role_denied_and_cross_school_fails_closed(): void
    {
        $schoolA = $this->createSchool('SCH-72-U10-A', 'U10 School A');
        $schoolB = $this->createSchool('SCH-72-U10-B', 'U10 School B');
        $teacher = $this->actingAsGradesTeacher(schoolId: $schoolA);
        $this->bindSchool($schoolA);
        $graphA = $this->seedExamGradeGraph($schoolA, suffix: 'U10A');

        try {
            $this->app->make(CancelExamHandler::class)->handle(new CancelExamCommand(
                schoolId: $schoolA,
                examId: $graphA['exam_id'],
                actorUserId: (int) $teacher->id,
                idempotencyKey: 'u10-denied',
            ));
            $this->fail('Expected AuthZ denial');
        } catch (ExamAuthorityDeniedException) {
            $this->assertTrue(true);
        }

        $manager = $this->actingAsGradesManagerForSchool($schoolA);
        $this->bindSchool($schoolA);
        $graphB = $this->seedExamGradeGraph($schoolB, suffix: 'U10B');
        $outboxBefore = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count();

        try {
            $this->app->make(CancelExamHandler::class)->handle(new CancelExamCommand(
                schoolId: $schoolA,
                examId: $graphB['exam_id'],
                actorUserId: (int) $manager->id,
                idempotencyKey: 'u10-cross',
            ));
            $this->fail('Expected cross-school denial');
        } catch (\Throwable) {
            $this->assertTrue(true);
        }

        $this->assertSame($outboxBefore, DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count());
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exams'), [
            'id' => $graphB['exam_id'],
            'status' => ExamStatus::Scheduled->value,
        ]);
    }

    #[Test]
    public function idempotency_conflict_fails_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-U10-4', 'U10 Idemp School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $first = $this->seedExamGradeGraph($schoolId, suffix: 'U104a');
        $second = $this->seedExamGradeGraph($schoolId, suffix: 'U104b');
        $handler = $this->app->make(CancelExamHandler::class);

        $handler->handle(new CancelExamCommand(
            schoolId: $schoolId,
            examId: $first['exam_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u10-conflict',
        ));

        $this->expectException(IdempotencyPayloadConflictException::class);
        $handler->handle(new CancelExamCommand(
            schoolId: $schoolId,
            examId: $second['exam_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u10-conflict',
        ));
    }

    #[Test]
    public function u09_present_semantics_remain_unchanged(): void
    {
        $schoolId = $this->createSchool('SCH-72-U10-9', 'U10 U09 Regression');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U109');

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::InProgress->value]);
        DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
            ->where('id', $graph['exam_enrollment_id'])
            ->update(['status' => ExamEnrollmentStatus::Confirmed->value]);

        $result = $this->app->make(PresentExamEnrollmentHandler::class)->handle(new PresentExamEnrollmentCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            examSessionId: $graph['session_id'],
            enrollmentId: $graph['enrollment_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u10-u09-reg',
        ));

        $this->assertSame(ExamEnrollmentStatus::Present->value, $result->status);
        $this->assertFalse($result->noop);
    }

    /**
     * @param  array{
     *   exam_id:int,year_id:int,school_id?:int
     * }  $primary
     * @return array{session_id:int,exam_enrollment_id:int,enrollment_id:int}
     */
    private function addSiblingSessionEnrollment(array $primary, int $schoolId, string $suffix): array
    {
        $subjectId = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'SUB'.substr(uniqid(), -4),
            'name' => 'Sibling Subject '.$suffix,
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $primary['year_id']);

        $sessionId = (int) DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))->insertGetId([
            'exam_id' => $primary['exam_id'],
            'school_id' => $schoolId,
            'subject_id' => $subjectId,
            'session_date' => '2026-11-06',
            'start_time' => '12:00:00',
            'end_time' => '14:00:00',
            'max_grade' => 100,
            'pass_grade' => 50,
            'status' => ExamSessionStatus::Scheduled->value,
            'created_at' => now(),
        ]);

        $examEnrollmentId = (int) DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))->insertGetId([
            'exam_session_id' => $sessionId,
            'school_id' => $schoolId,
            'enrollment_id' => (int) $enrollment->id,
            'seat_number' => 'B1',
            'status' => ExamEnrollmentStatus::Registered->value,
            'created_at' => now(),
        ]);

        return [
            'session_id' => $sessionId,
            'exam_enrollment_id' => $examEnrollmentId,
            'enrollment_id' => (int) $enrollment->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function latestOutboxPayload(string $eventType, string $like): array
    {
        $json = (string) DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
            ->where('event_type', $eventType)
            ->where('payload', 'like', $like)
            ->orderByDesc('id')
            ->value('payload');

        return json_decode($json, true) ?: [];
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
