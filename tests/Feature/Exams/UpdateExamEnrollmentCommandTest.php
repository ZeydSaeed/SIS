<?php

namespace Tests\Feature\Exams;

use App\Application\Exams\Commands\UpdateExamEnrollmentCommand;
use App\Application\Exams\Commands\UpdateExamEnrollmentHandler;
use App\Database\SchemaHelper;
use App\Domain\Exams\Events\ExamEnrollmentUpdated;
use App\Domain\Exams\Exceptions\ExamAuthorityDeniedException;
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

final class UpdateExamEnrollmentCommandTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function grades_manager_confirms_registered_with_outbox_and_idempotency(): void
    {
        $schoolId = $this->createSchool('SCH-72-U07-1', 'Update Enroll School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U071');
        $handler = $this->app->make(UpdateExamEnrollmentHandler::class);

        $gradesBefore = $this->gradeCount($schoolId);
        $attendanceBefore = $this->attendanceCount($schoolId);

        $result = $handler->handle($this->cmd(
            $schoolId,
            $graph,
            (int) $user->id,
            'u07-confirm-1',
            status: ExamEnrollmentStatus::Confirmed->value,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(ExamEnrollmentStatus::Confirmed->value, $result->status);
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_enrollments'), [
            'id' => $graph['exam_enrollment_id'],
            'status' => ExamEnrollmentStatus::Confirmed->value,
            'exam_session_id' => $graph['session_id'],
            'enrollment_id' => $graph['enrollment_id'],
            'school_id' => $schoolId,
        ]);

        $payload = json_decode(
            (string) DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamEnrollmentUpdated::class)
                ->orderByDesc('id')
                ->value('payload'),
            true,
        );
        $this->assertSame('exam_enrollment_update', $payload['cause'] ?? null);
        $this->assertSame(ExamEnrollmentStatus::Registered->value, $payload['previous_status'] ?? null);

        $this->assertTrue(
            DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))
                ->where('key', 'u07-confirm-1')
                ->where('command_name', UpdateExamEnrollmentHandler::COMMAND_NAME)
                ->exists()
        );

        $this->assertSame($gradesBefore, $this->gradeCount($schoolId));
        $this->assertSame($attendanceBefore, $this->attendanceCount($schoolId));

        $replay = $handler->handle($this->cmd(
            $schoolId,
            $graph,
            (int) $user->id,
            'u07-confirm-1',
            status: ExamEnrollmentStatus::Confirmed->value,
        ));
        $this->assertTrue($replay->fromIdempotencyCache);
        $this->assertSame(
            1,
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamEnrollmentUpdated::class)
                ->where('payload', 'like', '%"exam_enrollment_id":'.$graph['exam_enrollment_id'].'%')
                ->count()
        );
    }

    #[Test]
    public function authorized_absent_transitions_from_registered_confirmed_and_present(): void
    {
        $schoolId = $this->createSchool('SCH-72-U07-2', 'Absent Transitions School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $handler = $this->app->make(UpdateExamEnrollmentHandler::class);

        foreach ([
            [ExamEnrollmentStatus::Registered, 'reg'],
            [ExamEnrollmentStatus::Confirmed, 'conf'],
            [ExamEnrollmentStatus::Present, 'pres'],
        ] as [$from, $suffix]) {
            $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U072'.$suffix);
            DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
                ->where('id', $graph['exam_enrollment_id'])
                ->update(['status' => $from->value]);

            $result = $handler->handle($this->cmd(
                $schoolId,
                $graph,
                (int) $user->id,
                'u07-absent-'.$suffix,
                status: ExamEnrollmentStatus::Absent->value,
            ));
            $this->assertSame(ExamEnrollmentStatus::Absent->value, $result->status);
        }
    }

    #[Test]
    public function missing_authorization_fails_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-U07-3', 'Update Deny School');
        $teacher = $this->actingAsGradesTeacher(schoolId: $schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U073');

        $this->expectException(ExamAuthorityDeniedException::class);
        $this->app->make(UpdateExamEnrollmentHandler::class)->handle($this->cmd(
            $schoolId,
            $graph,
            (int) $teacher->id,
            'u07-denied',
            status: ExamEnrollmentStatus::Confirmed->value,
        ));
    }

    #[Test]
    public function exam_session_cancel_permission_remains_absent(): void
    {
        $this->assertArrayNotHasKey('exam.session.cancel', config('security.permissions'));
        $this->assertNotContains('exam.session.cancel', config('security.roles.grades_manager'));
    }

    #[Test]
    public function cross_school_seat_fails_closed_without_mutation_outbox_or_idempotency(): void
    {
        $schoolA = $this->createSchool('SCH-72-U07-A', 'Update School A');
        $schoolB = $this->createSchool('SCH-72-U07-B', 'Update School B');
        $user = $this->actingAsGradesManagerForSchool($schoolA);
        $this->bindSchool($schoolA);
        $graphB = $this->seedExamGradeGraph($schoolB, suffix: 'U07B');

        $outboxBefore = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count();
        $idempBefore = DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))->count();

        try {
            $this->app->make(UpdateExamEnrollmentHandler::class)->handle(new UpdateExamEnrollmentCommand(
                schoolId: $schoolA,
                examEnrollmentId: $graphB['exam_enrollment_id'],
                examSessionId: $graphB['session_id'],
                enrollmentId: $graphB['enrollment_id'],
                actorUserId: (int) $user->id,
                idempotencyKey: 'u07-cross',
                status: ExamEnrollmentStatus::Confirmed->value,
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
    public function identity_mismatch_on_session_or_enrollment_fails_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-U07-4', 'Identity School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U074');
        $handler = $this->app->make(UpdateExamEnrollmentHandler::class);

        try {
            $handler->handle(new UpdateExamEnrollmentCommand(
                schoolId: $schoolId,
                examEnrollmentId: $graph['exam_enrollment_id'],
                examSessionId: $graph['session_id'] + 99999,
                enrollmentId: $graph['enrollment_id'],
                actorUserId: (int) $user->id,
                idempotencyKey: 'u07-bad-session',
                status: ExamEnrollmentStatus::Confirmed->value,
            ));
            $this->fail('Expected session identity rejection');
        } catch (ExamValidationException) {
            $this->assertTrue(true);
        }

        $this->expectException(ExamValidationException::class);
        $handler->handle(new UpdateExamEnrollmentCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            examSessionId: $graph['session_id'],
            enrollmentId: $graph['enrollment_id'] + 99999,
            actorUserId: (int) $user->id,
            idempotencyKey: 'u07-bad-enrollment',
            status: ExamEnrollmentStatus::Confirmed->value,
        ));
    }

    #[Test]
    public function confirmed_to_present_and_any_to_withdrawn_are_forbidden(): void
    {
        $schoolId = $this->createSchool('SCH-72-U07-5', 'Forbidden Transitions School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $handler = $this->app->make(UpdateExamEnrollmentHandler::class);

        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U075a');
        DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
            ->where('id', $graph['exam_enrollment_id'])
            ->update(['status' => ExamEnrollmentStatus::Confirmed->value]);

        try {
            $handler->handle($this->cmd(
                $schoolId,
                $graph,
                (int) $user->id,
                'u07-to-present',
                status: ExamEnrollmentStatus::Present->value,
            ));
            $this->fail('Expected Present rejection');
        } catch (ExamUpdateForbiddenException) {
            $this->assertTrue(true);
        }

        $this->expectException(ExamUpdateForbiddenException::class);
        $handler->handle($this->cmd(
            $schoolId,
            $graph,
            (int) $user->id,
            'u07-to-withdrawn',
            status: ExamEnrollmentStatus::Withdrawn->value,
        ));
    }

    #[Test]
    public function restore_from_absent_or_withdrawn_is_forbidden(): void
    {
        $schoolId = $this->createSchool('SCH-72-U07-6', 'Restore Forbidden School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $handler = $this->app->make(UpdateExamEnrollmentHandler::class);

        foreach ([
            [ExamEnrollmentStatus::Absent, ExamEnrollmentStatus::Registered, 'a-reg'],
            [ExamEnrollmentStatus::Absent, ExamEnrollmentStatus::Confirmed, 'a-conf'],
            [ExamEnrollmentStatus::Absent, ExamEnrollmentStatus::Present, 'a-pres'],
            [ExamEnrollmentStatus::Withdrawn, ExamEnrollmentStatus::Registered, 'w-reg'],
            [ExamEnrollmentStatus::Withdrawn, ExamEnrollmentStatus::Confirmed, 'w-conf'],
            [ExamEnrollmentStatus::Withdrawn, ExamEnrollmentStatus::Present, 'w-pres'],
        ] as [$from, $to, $suffix]) {
            $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U076'.$suffix);
            DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
                ->where('id', $graph['exam_enrollment_id'])
                ->update(['status' => $from->value]);

            try {
                $handler->handle($this->cmd(
                    $schoolId,
                    $graph,
                    (int) $user->id,
                    'u07-restore-'.$suffix,
                    status: $to->value,
                ));
                $this->fail("Expected restore rejection for {$suffix}");
            } catch (ExamUpdateForbiddenException) {
                $this->assertTrue(true);
            }
        }
    }

    #[Test]
    public function confirm_allowed_on_scheduled_in_progress_and_completed_not_cancelled(): void
    {
        $schoolId = $this->createSchool('SCH-72-U07-7', 'Session Status School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $handler = $this->app->make(UpdateExamEnrollmentHandler::class);

        foreach ([
            ExamSessionStatus::Scheduled,
            ExamSessionStatus::InProgress,
            ExamSessionStatus::Completed,
        ] as $index => $sessionStatus) {
            $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U077'.$index);
            DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
                ->where('id', $graph['session_id'])
                ->update(['status' => $sessionStatus->value]);

            $result = $handler->handle($this->cmd(
                $schoolId,
                $graph,
                (int) $user->id,
                'u07-confirm-'.$sessionStatus->name,
                status: ExamEnrollmentStatus::Confirmed->value,
            ));
            $this->assertSame(ExamEnrollmentStatus::Confirmed->value, $result->status);
        }
    }

    #[Test]
    public function any_mutation_on_cancelled_session_is_rejected(): void
    {
        $schoolId = $this->createSchool('SCH-72-U07-8', 'Cancelled Session Update School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $handler = $this->app->make(UpdateExamEnrollmentHandler::class);

        $graphConfirm = $this->seedExamGradeGraph($schoolId, suffix: 'U078c');
        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graphConfirm['session_id'])
            ->update(['status' => ExamSessionStatus::Cancelled->value]);

        try {
            $handler->handle($this->cmd(
                $schoolId,
                $graphConfirm,
                (int) $user->id,
                'u07-cancel-confirm',
                status: ExamEnrollmentStatus::Confirmed->value,
            ));
            $this->fail('Expected cancelled confirm rejection');
        } catch (ExamValidationException) {
            $this->assertTrue(true);
        }

        $graphAbsent = $this->seedExamGradeGraph($schoolId, suffix: 'U078a');
        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graphAbsent['session_id'])
            ->update(['status' => ExamSessionStatus::Cancelled->value]);

        $this->expectException(ExamValidationException::class);
        $handler->handle($this->cmd(
            $schoolId,
            $graphAbsent,
            (int) $user->id,
            'u07-cancel-absent',
            status: ExamEnrollmentStatus::Absent->value,
        ));
    }

    #[Test]
    public function seat_number_updates_when_not_frozen_and_optional_without_uniqueness(): void
    {
        $schoolId = $this->createSchool('SCH-72-U07-9', 'Seat Number School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $handler = $this->app->make(UpdateExamEnrollmentHandler::class);

        $first = $this->seedExamGradeGraph($schoolId, suffix: 'U079a');
        $second = $this->seedExamGradeGraph($schoolId, suffix: 'U079b');

        $result = $handler->handle($this->cmd(
            $schoolId,
            $first,
            (int) $user->id,
            'u07-seat-1',
            seatNumberProvided: true,
            seatNumber: 'B9',
        ));
        $this->assertTrue($result->success);
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_enrollments'), [
            'id' => $first['exam_enrollment_id'],
            'seat_number' => 'B9',
        ]);

        $duplicateSeat = $handler->handle($this->cmd(
            $schoolId,
            $second,
            (int) $user->id,
            'u07-seat-2',
            seatNumberProvided: true,
            seatNumber: 'B9',
        ));
        $this->assertTrue($duplicateSeat->success);
    }

    #[Test]
    public function present_and_current_grade_freeze_seat_number(): void
    {
        $schoolId = $this->createSchool('SCH-72-U07-10', 'Seat Freeze School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $handler = $this->app->make(UpdateExamEnrollmentHandler::class);

        $presentGraph = $this->seedExamGradeGraph($schoolId, suffix: 'U0710p');
        DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
            ->where('id', $presentGraph['exam_enrollment_id'])
            ->update([
                'status' => ExamEnrollmentStatus::Present->value,
                'seat_number' => 'P1',
            ]);

        try {
            $handler->handle($this->cmd(
                $schoolId,
                $presentGraph,
                (int) $user->id,
                'u07-freeze-present',
                seatNumberProvided: true,
                seatNumber: 'P2',
            ));
            $this->fail('Expected Present seat freeze');
        } catch (ExamUpdateForbiddenException) {
            $this->assertTrue(true);
        }

        $gradeGraph = $this->seedExamGradeGraph($schoolId, suffix: 'U0710g');
        DB::table(SchemaHelper::qualified('exams', 'student_grades'))->insert([
            'academic_year_id' => $gradeGraph['year_id'],
            'school_id' => $schoolId,
            'exam_enrollment_id' => $gradeGraph['exam_enrollment_id'],
            'exam_session_id' => $gradeGraph['session_id'],
            'enrollment_id' => $gradeGraph['enrollment_id'],
            'student_id' => $gradeGraph['student_id'],
            'subject_id' => $gradeGraph['subject_id'],
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

        $this->expectException(ExamUpdateForbiddenException::class);
        $handler->handle($this->cmd(
            $schoolId,
            $gradeGraph,
            (int) $user->id,
            'u07-freeze-grade',
            seatNumberProvided: true,
            seatNumber: 'G2',
        ));
    }

    #[Test]
    public function empty_update_is_rejected_without_outbox(): void
    {
        $schoolId = $this->createSchool('SCH-72-U07-11', 'Empty Update School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U0711');
        $outboxBefore = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count();

        try {
            $this->app->make(UpdateExamEnrollmentHandler::class)->handle($this->cmd(
                $schoolId,
                $graph,
                (int) $user->id,
                'u07-empty',
            ));
            $this->fail('Expected empty update rejection');
        } catch (ExamValidationException) {
            $this->assertTrue(true);
        }

        try {
            $this->app->make(UpdateExamEnrollmentHandler::class)->handle($this->cmd(
                $schoolId,
                $graph,
                (int) $user->id,
                'u07-same-status',
                status: ExamEnrollmentStatus::Registered->value,
            ));
            $this->fail('Expected same-status rejection');
        } catch (ExamValidationException) {
            $this->assertTrue(true);
        }

        $this->assertSame($outboxBefore, DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count());
    }

    #[Test]
    public function idempotency_conflict_and_failed_validation_atomicity(): void
    {
        $schoolId = $this->createSchool('SCH-72-U07-12', 'Idemp Atomic School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U0712');
        $handler = $this->app->make(UpdateExamEnrollmentHandler::class);

        $handler->handle($this->cmd(
            $schoolId,
            $graph,
            (int) $user->id,
            'u07-conflict',
            status: ExamEnrollmentStatus::Confirmed->value,
        ));

        try {
            $handler->handle($this->cmd(
                $schoolId,
                $graph,
                (int) $user->id,
                'u07-conflict',
                status: ExamEnrollmentStatus::Absent->value,
            ));
            $this->fail('Expected idempotency conflict');
        } catch (IdempotencyPayloadConflictException) {
            $this->assertTrue(true);
        }

        $graphFail = $this->seedExamGradeGraph($schoolId, suffix: 'U0712f');
        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graphFail['session_id'])
            ->update(['status' => ExamSessionStatus::Cancelled->value]);

        $outboxBefore = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count();
        $idempBefore = DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))->count();
        $statusBefore = (int) DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
            ->where('id', $graphFail['exam_enrollment_id'])
            ->value('status');

        try {
            $handler->handle($this->cmd(
                $schoolId,
                $graphFail,
                (int) $user->id,
                'u07-fail-atomic',
                status: ExamEnrollmentStatus::Confirmed->value,
            ));
            $this->fail('Expected cancelled validation failure');
        } catch (ExamValidationException) {
            $this->assertTrue(true);
        }

        $this->assertSame($outboxBefore, DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count());
        $this->assertSame($idempBefore, DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))->count());
        $this->assertSame(
            $statusBefore,
            (int) DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
                ->where('id', $graphFail['exam_enrollment_id'])
                ->value('status')
        );
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
        ?int $status = null,
        bool $seatNumberProvided = false,
        ?string $seatNumber = null,
    ): UpdateExamEnrollmentCommand {
        return new UpdateExamEnrollmentCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            examSessionId: $graph['session_id'],
            enrollmentId: $graph['enrollment_id'],
            actorUserId: $actorUserId,
            idempotencyKey: $idempotencyKey,
            status: $status,
            seatNumberProvided: $seatNumberProvided,
            seatNumber: $seatNumber,
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
