<?php

namespace Tests\Feature\Exams;

use App\Application\Exams\Commands\UpdateExamSessionCommand;
use App\Application\Exams\Commands\UpdateExamSessionHandler;
use App\Database\SchemaHelper;
use App\Domain\Exams\Events\ExamSessionUpdated;
use App\Domain\Exams\Exceptions\ExamAuthorityDeniedException;
use App\Domain\Exams\Exceptions\ExamUpdateForbiddenException;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Exceptions\IdempotencyPayloadConflictException;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Security\Context\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;
use Tests\TestCase;

final class UpdateExamSessionCommandTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function grades_manager_updates_allowlisted_fields_with_outbox_and_idempotency(): void
    {
        $schoolId = $this->createSchool('SCH-72-U1', 'Session Update School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U1');
        $roomId = $this->seedRoomForSchool($schoolId, 'U1');
        $handler = $this->app->make(UpdateExamSessionHandler::class);

        $gradesBefore = $this->gradeCount($schoolId);
        $attendanceBefore = $this->attendanceCount($schoolId);

        $result = $handler->handle(new UpdateExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'update-session-1',
            sessionDate: '2026-11-10',
            startTime: '13:00:00',
            endTime: '15:00:00',
            roomId: $roomId,
            roomIdProvided: true,
            maxGrade: 90,
            passGrade: 45,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(ExamSessionStatus::Scheduled->value, $result->status);

        $row = DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->first();
        $this->assertSame('2026-11-10', (string) $row->session_date);
        $this->assertSame($roomId, (int) $row->room_id);
        $this->assertSame(90, (int) $row->max_grade);
        $this->assertSame($graph['exam_id'], (int) $row->exam_id);
        $this->assertSame($graph['subject_id'], (int) $row->subject_id);
        $this->assertSame(ExamSessionStatus::Scheduled->value, (int) $row->status);

        $outboxPayload = json_decode(
            (string) DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', ExamSessionUpdated::class)
                ->orderByDesc('id')
                ->value('payload'),
            true,
        );
        $this->assertSame('exam_session_update', $outboxPayload['cause'] ?? null);

        $this->assertTrue(
            DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))
                ->where('key', 'update-session-1')
                ->where('command_name', UpdateExamSessionHandler::COMMAND_NAME)
                ->exists()
        );

        $this->assertSame($gradesBefore, $this->gradeCount($schoolId));
        $this->assertSame($attendanceBefore, $this->attendanceCount($schoolId));

        $replay = $handler->handle(new UpdateExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'update-session-1',
            sessionDate: '2026-11-10',
            startTime: '13:00:00',
            endTime: '15:00:00',
            roomId: $roomId,
            roomIdProvided: true,
            maxGrade: 90,
            passGrade: 45,
        ));
        $this->assertTrue($replay->fromIdempotencyCache);
    }

    #[Test]
    public function missing_authorization_fails_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-U2', 'Session Update Deny');
        $teacher = $this->actingAsGradesTeacher(schoolId: $schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U2');

        $this->expectException(ExamAuthorityDeniedException::class);
        $this->app->make(UpdateExamSessionHandler::class)->handle(new UpdateExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $teacher->id,
            idempotencyKey: 'update-denied',
            sessionDate: '2026-11-11',
        ));
    }

    #[Test]
    public function cross_school_session_update_fails_closed(): void
    {
        $schoolA = $this->createSchool('SCH-72-UA', 'Update School A');
        $schoolB = $this->createSchool('SCH-72-UB', 'Update School B');
        $user = $this->actingAsGradesManagerForSchool($schoolA);
        $this->bindSchool($schoolA);
        $graphB = $this->seedExamGradeGraph($schoolB, suffix: 'UB');

        $this->expectException(ExamValidationException::class);
        $this->app->make(UpdateExamSessionHandler::class)->handle(new UpdateExamSessionCommand(
            schoolId: $schoolA,
            examSessionId: $graphB['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'update-cross',
            sessionDate: '2026-11-11',
        ));
    }

    #[Test]
    public function cross_school_room_fails_closed(): void
    {
        $schoolA = $this->createSchool('SCH-72-URA', 'Update Room A');
        $schoolB = $this->createSchool('SCH-72-URB', 'Update Room B');
        $user = $this->actingAsGradesManagerForSchool($schoolA);
        $this->bindSchool($schoolA);
        $graph = $this->seedExamGradeGraph($schoolA, suffix: 'URA');
        $foreignRoom = $this->seedRoomForSchool($schoolB, 'URB');

        $this->expectException(ExamValidationException::class);
        $this->app->make(UpdateExamSessionHandler::class)->handle(new UpdateExamSessionCommand(
            schoolId: $schoolA,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'update-bad-room',
            roomId: $foreignRoom,
            roomIdProvided: true,
        ));
    }

    #[Test]
    public function update_does_not_move_or_reassign_identity(): void
    {
        $schoolId = $this->createSchool('SCH-72-UM', 'No Move School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'UM');

        $this->app->make(UpdateExamSessionHandler::class)->handle(new UpdateExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'update-no-move',
            sessionDate: '2026-11-12',
        ));

        $row = DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->first();
        $this->assertSame($graph['exam_id'], (int) $row->exam_id);
        $this->assertSame($graph['subject_id'], (int) $row->subject_id);
        $this->assertSame($schoolId, (int) $row->school_id);
        $this->assertFalse(property_exists($row, 'moved_to_session_id'));
    }

    #[Test]
    public function update_rejects_non_scheduled_status_and_does_not_transition_status(): void
    {
        $schoolId = $this->createSchool('SCH-72-US', 'Status Block School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'US');

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::InProgress->value]);

        $this->expectException(ExamUpdateForbiddenException::class);
        $this->app->make(UpdateExamSessionHandler::class)->handle(new UpdateExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'update-in-progress',
            sessionDate: '2026-11-13',
        ));
    }

    #[Test]
    public function current_grade_freezes_max_and_pass_grade(): void
    {
        $schoolId = $this->createSchool('SCH-72-UG', 'Grade Freeze School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'UG');

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

        $this->expectException(ExamUpdateForbiddenException::class);
        $this->app->make(UpdateExamSessionHandler::class)->handle(new UpdateExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'update-frozen-max',
            maxGrade: 80,
        ));
    }

    #[Test]
    public function conflicting_idempotency_payload_fails_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-UI', 'Update Idemp School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'UI');
        $handler = $this->app->make(UpdateExamSessionHandler::class);

        $handler->handle(new UpdateExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'update-conflict',
            sessionDate: '2026-11-14',
        ));

        $this->expectException(IdempotencyPayloadConflictException::class);
        $handler->handle(new UpdateExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'update-conflict',
            sessionDate: '2026-11-15',
        ));
    }

    #[Test]
    public function update_does_not_introduce_overlap_or_capacity_enforcement(): void
    {
        $schoolId = $this->createSchool('SCH-72-UO', 'No Overlap School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'UO');
        $roomId = $this->seedRoomForSchool($schoolId, 'UO');

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))->insert([
            'exam_id' => $graph['exam_id'],
            'school_id' => $schoolId,
            'subject_id' => $graph['subject_id'],
            'session_date' => '2026-11-20',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'room_id' => $roomId,
            'max_grade' => 100,
            'pass_grade' => 50,
            'status' => ExamSessionStatus::Scheduled->value,
            'created_at' => now(),
        ]);

        $result = $this->app->make(UpdateExamSessionHandler::class)->handle(new UpdateExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'update-overlap-ok',
            sessionDate: '2026-11-20',
            startTime: '09:00:00',
            endTime: '11:00:00',
            roomId: $roomId,
            roomIdProvided: true,
        ));

        $this->assertTrue($result->success);
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
