<?php

namespace Tests\Feature\Exams;

use App\Application\Exams\Commands\CreateExamEnrollmentCommand;
use App\Application\Exams\Commands\CreateExamEnrollmentHandler;
use App\Database\SchemaHelper;
use App\Domain\Exams\Events\ExamEnrollmentCreated;
use App\Domain\Exams\Exceptions\AcademicEnrollmentInactiveException;
use App\Domain\Exams\Exceptions\ExamAuthorityDeniedException;
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

final class CreateExamEnrollmentCommandTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function grades_manager_creates_registered_enrollment_with_outbox_and_idempotency(): void
    {
        $schoolId = $this->createSchool('SCH-72-E1', 'Enrollment Create School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'E1');
        $academic = $this->createActiveEnrollmentForSchool($schoolId, $graph['year_id']);
        $handler = $this->app->make(CreateExamEnrollmentHandler::class);

        $gradesBefore = $this->gradeCount($schoolId);
        $attendanceBefore = $this->attendanceCount($schoolId);

        $result = $handler->handle(new CreateExamEnrollmentCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            enrollmentId: (int) $academic->id,
            actorUserId: (int) $user->id,
            idempotencyKey: 'create-enrollment-1',
            seatNumber: 'B12',
        ));

        $this->assertTrue($result->success);
        $this->assertSame(ExamEnrollmentStatus::Registered->value, $result->status);
        $this->assertTrue(ExamEnrollmentStatus::from((int) $result->status)->isActiveSeat());

        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_enrollments'), [
            'id' => $result->examEnrollmentId,
            'exam_session_id' => $graph['session_id'],
            'enrollment_id' => $academic->id,
            'school_id' => $schoolId,
            'status' => ExamEnrollmentStatus::Registered->value,
            'seat_number' => 'B12',
        ]);

        $payload = json_decode(
            (string) DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamEnrollmentCreated::class)
                ->orderByDesc('id')
                ->value('payload'),
            true,
        );
        $this->assertSame('exam_enrollment_create', $payload['cause'] ?? null);

        $this->assertTrue(
            DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))
                ->where('key', 'create-enrollment-1')
                ->where('command_name', CreateExamEnrollmentHandler::COMMAND_NAME)
                ->exists()
        );

        $this->assertSame($gradesBefore, $this->gradeCount($schoolId));
        $this->assertSame($attendanceBefore, $this->attendanceCount($schoolId));

        $replay = $handler->handle(new CreateExamEnrollmentCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            enrollmentId: (int) $academic->id,
            actorUserId: (int) $user->id,
            idempotencyKey: 'create-enrollment-1',
            seatNumber: 'B12',
        ));
        $this->assertTrue($replay->fromIdempotencyCache);
        $this->assertSame($result->examEnrollmentId, $replay->examEnrollmentId);
        $this->assertSame(
            1,
            DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
                ->where('exam_session_id', $graph['session_id'])
                ->where('enrollment_id', $academic->id)
                ->count()
        );
        $this->assertSame(
            1,
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamEnrollmentCreated::class)
                ->where('payload', 'like', '%"enrollment_id":'.(int) $academic->id.'%')
                ->count()
        );
    }

    #[Test]
    public function create_without_seat_number_is_valid(): void
    {
        $schoolId = $this->createSchool('SCH-72-E2', 'No Seat School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'E2');
        $academic = $this->createActiveEnrollmentForSchool($schoolId, $graph['year_id']);

        $result = $this->app->make(CreateExamEnrollmentHandler::class)->handle(new CreateExamEnrollmentCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            enrollmentId: (int) $academic->id,
            actorUserId: (int) $user->id,
            idempotencyKey: 'create-no-seat',
        ));

        $this->assertTrue($result->success);
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_enrollments'), [
            'id' => $result->examEnrollmentId,
            'seat_number' => null,
        ]);
    }

    #[Test]
    public function missing_authorization_fails_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-E3', 'Enrollment Deny School');
        $teacher = $this->actingAsGradesTeacher(schoolId: $schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'E3');
        $academic = $this->createActiveEnrollmentForSchool($schoolId, $graph['year_id']);

        $this->expectException(ExamAuthorityDeniedException::class);
        $this->app->make(CreateExamEnrollmentHandler::class)->handle(new CreateExamEnrollmentCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            enrollmentId: (int) $academic->id,
            actorUserId: (int) $teacher->id,
            idempotencyKey: 'create-denied',
        ));
    }

    #[Test]
    public function scheduled_in_progress_and_completed_sessions_allow_create(): void
    {
        $schoolId = $this->createSchool('SCH-72-E4', 'Status Allow School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $handler = $this->app->make(CreateExamEnrollmentHandler::class);

        foreach ([
            ExamSessionStatus::Scheduled,
            ExamSessionStatus::InProgress,
            ExamSessionStatus::Completed,
        ] as $index => $status) {
            $graph = $this->seedExamGradeGraph($schoolId, suffix: 'E4'.$index);
            DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
                ->where('id', $graph['session_id'])
                ->update(['status' => $status->value]);
            $academic = $this->createActiveEnrollmentForSchool($schoolId, $graph['year_id']);

            $result = $handler->handle(new CreateExamEnrollmentCommand(
                schoolId: $schoolId,
                examSessionId: $graph['session_id'],
                enrollmentId: (int) $academic->id,
                actorUserId: (int) $user->id,
                idempotencyKey: 'create-status-'.$status->name,
            ));
            $this->assertSame(ExamEnrollmentStatus::Registered->value, $result->status);
        }
    }

    #[Test]
    public function cancelled_session_is_rejected(): void
    {
        $schoolId = $this->createSchool('SCH-72-E5', 'Cancelled Session School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'E5');
        $academic = $this->createActiveEnrollmentForSchool($schoolId, $graph['year_id']);

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::Cancelled->value]);

        $this->expectException(ExamValidationException::class);
        $this->app->make(CreateExamEnrollmentHandler::class)->handle(new CreateExamEnrollmentCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            enrollmentId: (int) $academic->id,
            actorUserId: (int) $user->id,
            idempotencyKey: 'create-cancelled-session',
        ));
    }

    #[Test]
    public function inactive_academic_enrollment_is_rejected(): void
    {
        $schoolId = $this->createSchool('SCH-72-E6', 'Inactive Enrollment School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'E6');
        $academic = $this->createActiveEnrollmentForSchool($schoolId, $graph['year_id']);

        DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('id', $academic->id)
            ->update([
                'status' => 2,
                'effective_to' => '2026-10-01',
            ]);

        $this->expectException(AcademicEnrollmentInactiveException::class);
        $this->app->make(CreateExamEnrollmentHandler::class)->handle(new CreateExamEnrollmentCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            enrollmentId: (int) $academic->id,
            actorUserId: (int) $user->id,
            idempotencyKey: 'create-inactive',
        ));
    }

    #[Test]
    public function academic_year_mismatch_is_rejected(): void
    {
        $schoolId = $this->createSchool('SCH-72-E7', 'Year Mismatch School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'E7');
        $otherYear = $this->createAcademicYear('AY-MISMATCH-E7');
        $academic = $this->createActiveEnrollmentForSchool($schoolId, $otherYear);

        $this->expectException(ExamValidationException::class);
        $this->app->make(CreateExamEnrollmentHandler::class)->handle(new CreateExamEnrollmentCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            enrollmentId: (int) $academic->id,
            actorUserId: (int) $user->id,
            idempotencyKey: 'create-year-mismatch',
        ));
    }

    #[Test]
    public function cross_school_session_and_enrollment_fail_closed(): void
    {
        $schoolA = $this->createSchool('SCH-72-EA', 'Enrollment School A');
        $schoolB = $this->createSchool('SCH-72-EB', 'Enrollment School B');
        $user = $this->actingAsGradesManagerForSchool($schoolA);
        $this->bindSchool($schoolA);
        $graphA = $this->seedExamGradeGraph($schoolA, suffix: 'EA');
        $graphB = $this->seedExamGradeGraph($schoolB, suffix: 'EB');
        $handler = $this->app->make(CreateExamEnrollmentHandler::class);

        try {
            $handler->handle(new CreateExamEnrollmentCommand(
                schoolId: $schoolA,
                examSessionId: $graphB['session_id'],
                enrollmentId: $graphA['enrollment_id'],
                actorUserId: (int) $user->id,
                idempotencyKey: 'cross-session',
            ));
            $this->fail('Expected session cross-school rejection');
        } catch (ExamValidationException) {
            $this->assertTrue(true);
        }

        $this->expectException(ExamValidationException::class);
        $handler->handle(new CreateExamEnrollmentCommand(
            schoolId: $schoolA,
            examSessionId: $graphA['session_id'],
            enrollmentId: $graphB['enrollment_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'cross-enrollment',
        ));
    }

    #[Test]
    public function duplicate_seat_rejected_and_same_enrollment_other_session_allowed(): void
    {
        $schoolId = $this->createSchool('SCH-72-E8', 'Duplicate Seat School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'E8');
        $handler = $this->app->make(CreateExamEnrollmentHandler::class);

        try {
            $handler->handle(new CreateExamEnrollmentCommand(
                schoolId: $schoolId,
                examSessionId: $graph['session_id'],
                enrollmentId: $graph['enrollment_id'],
                actorUserId: (int) $user->id,
                idempotencyKey: 'dup-seat',
            ));
            $this->fail('Expected duplicate rejection');
        } catch (ExamValidationException) {
            $this->assertTrue(true);
        }

        $secondSessionId = (int) DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))->insertGetId([
            'exam_id' => $graph['exam_id'],
            'school_id' => $schoolId,
            'subject_id' => $graph['subject_id'],
            'session_date' => '2026-11-06',
            'start_time' => '12:00:00',
            'end_time' => '14:00:00',
            'max_grade' => 100,
            'pass_grade' => 50,
            'status' => ExamSessionStatus::Scheduled->value,
            'created_at' => now(),
        ]);

        $result = $handler->handle(new CreateExamEnrollmentCommand(
            schoolId: $schoolId,
            examSessionId: $secondSessionId,
            enrollmentId: $graph['enrollment_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'other-session-seat',
        ));
        $this->assertTrue($result->success);
    }

    #[Test]
    public function capacity_is_not_enforced(): void
    {
        $schoolId = $this->createSchool('SCH-72-E9', 'No Capacity School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'E9');
        $handler = $this->app->make(CreateExamEnrollmentHandler::class);

        for ($i = 0; $i < 3; $i++) {
            $academic = $this->createActiveEnrollmentForSchool($schoolId, $graph['year_id']);
            $result = $handler->handle(new CreateExamEnrollmentCommand(
                schoolId: $schoolId,
                examSessionId: $graph['session_id'],
                enrollmentId: (int) $academic->id,
                actorUserId: (int) $user->id,
                idempotencyKey: 'cap-'.$i,
            ));
            $this->assertTrue($result->success);
        }

        $this->assertGreaterThanOrEqual(
            4,
            DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
                ->where('exam_session_id', $graph['session_id'])
                ->count()
        );
    }

    #[Test]
    public function idempotency_payload_conflict_fails_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-E10', 'Idemp Conflict School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'E10');
        $first = $this->createActiveEnrollmentForSchool($schoolId, $graph['year_id']);
        $second = $this->createActiveEnrollmentForSchool($schoolId, $graph['year_id']);
        $handler = $this->app->make(CreateExamEnrollmentHandler::class);

        $handler->handle(new CreateExamEnrollmentCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            enrollmentId: (int) $first->id,
            actorUserId: (int) $user->id,
            idempotencyKey: 'enroll-conflict',
        ));

        $this->expectException(IdempotencyPayloadConflictException::class);
        $handler->handle(new CreateExamEnrollmentCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            enrollmentId: (int) $second->id,
            actorUserId: (int) $user->id,
            idempotencyKey: 'enroll-conflict',
        ));
    }

    #[Test]
    public function exam_session_cancel_permission_remains_absent(): void
    {
        $this->assertArrayNotHasKey('exam.session.cancel', config('security.permissions'));
        $this->assertNotContains('exam.session.cancel', config('security.roles.grades_manager'));
    }

    #[Test]
    public function failed_validation_creates_no_outbox_or_idempotency(): void
    {
        $schoolId = $this->createSchool('SCH-72-E11', 'Fail Atomic School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'E11');
        $academic = $this->createActiveEnrollmentForSchool($schoolId, $graph['year_id']);

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::Cancelled->value]);

        $outboxBefore = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count();
        $idempBefore = DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))->count();

        try {
            $this->app->make(CreateExamEnrollmentHandler::class)->handle(new CreateExamEnrollmentCommand(
                schoolId: $schoolId,
                examSessionId: $graph['session_id'],
                enrollmentId: (int) $academic->id,
                actorUserId: (int) $user->id,
                idempotencyKey: 'fail-atomic',
            ));
            $this->fail('Expected validation failure');
        } catch (ExamValidationException) {
            $this->assertTrue(true);
        }

        $this->assertSame($outboxBefore, DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count());
        $this->assertSame($idempBefore, DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))->count());
        $this->assertSame(
            0,
            DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
                ->where('exam_session_id', $graph['session_id'])
                ->where('enrollment_id', $academic->id)
                ->count()
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
