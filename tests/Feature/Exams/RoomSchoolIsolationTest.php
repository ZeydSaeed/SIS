<?php

namespace Tests\Feature\Exams;

use App\Application\Exams\Commands\CreateExamSessionCommand;
use App\Application\Exams\Commands\CreateExamSessionHandler;
use App\Application\Exams\Commands\UpdateExamSessionCommand;
use App\Application\Exams\Commands\UpdateExamSessionHandler;
use App\Database\SchemaHelper;
use App\Domain\Exams\Events\ExamSessionCreated;
use App\Domain\Exams\Events\ExamSessionUpdated;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Security\Context\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;
use Tests\TestCase;

/**
 * Phase 7.2 Batch 6 U13 — HD-7.2-013 Option A (room → branch → school fail-closed).
 */
final class RoomSchoolIsolationTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function create_allows_same_school_room(): void
    {
        $schoolId = $this->createSchool('SCH-72-R13A', 'U13 Create Allow');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'R13A');
        $roomId = $this->seedRoomForSchool($schoolId, 'R13A');

        $gradesBefore = $this->gradeCount($schoolId);
        $attendanceBefore = $this->attendanceCount($schoolId);

        $result = $this->app->make(CreateExamSessionHandler::class)->handle(new CreateExamSessionCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            subjectId: $graph['subject_id'],
            sessionDate: '2026-12-01',
            startTime: '09:00:00',
            endTime: '11:00:00',
            actorUserId: (int) $user->id,
            idempotencyKey: 'u13-create-same-room',
            roomId: $roomId,
        ));

        $this->assertTrue($result->success);
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_sessions'), [
            'id' => $result->examSessionId,
            'school_id' => $schoolId,
            'room_id' => $roomId,
        ]);
        $this->assertSame($gradesBefore, $this->gradeCount($schoolId));
        $this->assertSame($attendanceBefore, $this->attendanceCount($schoolId));
    }

    #[Test]
    public function create_denies_cross_school_room_without_mutation_or_outbox(): void
    {
        $schoolA = $this->createSchool('SCH-72-R13B', 'U13 Create Deny A');
        $schoolB = $this->createSchool('SCH-72-R13C', 'U13 Create Deny B');
        $user = $this->actingAsGradesManagerForSchool($schoolA);
        $this->bindSchool($schoolA);
        $graph = $this->seedExamGradeGraph($schoolA, suffix: 'R13B');
        $foreignRoomId = $this->seedRoomForSchool($schoolB, 'R13C');

        $sessionsBefore = $this->sessionCountForExam($graph['exam_id']);
        $outboxBefore = $this->outboxCount(ExamSessionCreated::class);
        $gradesBefore = $this->gradeCount($schoolA);
        $attendanceBefore = $this->attendanceCount($schoolA);

        try {
            $this->app->make(CreateExamSessionHandler::class)->handle(new CreateExamSessionCommand(
                schoolId: $schoolA,
                examId: $graph['exam_id'],
                subjectId: $graph['subject_id'],
                sessionDate: '2026-12-02',
                startTime: '09:00:00',
                endTime: '11:00:00',
                actorUserId: (int) $user->id,
                idempotencyKey: 'u13-create-cross-room',
                roomId: $foreignRoomId,
            ));
            $this->fail('Expected ExamValidationException for cross-school room.');
        } catch (ExamValidationException $e) {
            $this->assertStringContainsString('current school', $e->getMessage());
        }

        $this->assertSame($sessionsBefore, $this->sessionCountForExam($graph['exam_id']));
        $this->assertSame($outboxBefore, $this->outboxCount(ExamSessionCreated::class));
        $this->assertSame($gradesBefore, $this->gradeCount($schoolA));
        $this->assertSame($attendanceBefore, $this->attendanceCount($schoolA));
    }

    #[Test]
    public function create_denies_unresolvable_room_fail_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-R13D', 'U13 Create Missing Room');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'R13D');
        $missingRoomId = 9_999_999_001;

        $this->expectException(ExamValidationException::class);
        $this->app->make(CreateExamSessionHandler::class)->handle(new CreateExamSessionCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            subjectId: $graph['subject_id'],
            sessionDate: '2026-12-03',
            startTime: '09:00:00',
            endTime: '11:00:00',
            actorUserId: (int) $user->id,
            idempotencyKey: 'u13-create-missing-room',
            roomId: $missingRoomId,
        ));
    }

    #[Test]
    public function create_without_room_id_preserves_existing_behavior(): void
    {
        $schoolId = $this->createSchool('SCH-72-R13E', 'U13 Create No Room');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'R13E');

        $result = $this->app->make(CreateExamSessionHandler::class)->handle(new CreateExamSessionCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            subjectId: $graph['subject_id'],
            sessionDate: '2026-12-04',
            startTime: '09:00:00',
            endTime: '11:00:00',
            actorUserId: (int) $user->id,
            idempotencyKey: 'u13-create-null-room',
            roomId: null,
        ));

        $this->assertTrue($result->success);
        $row = DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $result->examSessionId)
            ->first();
        $this->assertNull($row->room_id);
    }

    #[Test]
    public function update_allows_same_school_room(): void
    {
        $schoolId = $this->createSchool('SCH-72-R13F', 'U13 Update Allow');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'R13F');
        $roomId = $this->seedRoomForSchool($schoolId, 'R13F');

        $result = $this->app->make(UpdateExamSessionHandler::class)->handle(new UpdateExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u13-update-same-room',
            roomId: $roomId,
            roomIdProvided: true,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(
            $roomId,
            (int) DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
                ->where('id', $graph['session_id'])
                ->value('room_id')
        );
    }

    #[Test]
    public function update_denies_cross_school_room_without_mutation_or_outbox(): void
    {
        $schoolA = $this->createSchool('SCH-72-R13G', 'U13 Update Deny A');
        $schoolB = $this->createSchool('SCH-72-R13H', 'U13 Update Deny B');
        $user = $this->actingAsGradesManagerForSchool($schoolA);
        $this->bindSchool($schoolA);
        $graph = $this->seedExamGradeGraph($schoolA, suffix: 'R13G');
        $foreignRoom = $this->seedRoomForSchool($schoolB, 'R13H');

        $roomBefore = DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->value('room_id');
        $outboxBefore = $this->outboxCount(ExamSessionUpdated::class);
        $gradesBefore = $this->gradeCount($schoolA);
        $attendanceBefore = $this->attendanceCount($schoolA);

        try {
            $this->app->make(UpdateExamSessionHandler::class)->handle(new UpdateExamSessionCommand(
                schoolId: $schoolA,
                examSessionId: $graph['session_id'],
                actorUserId: (int) $user->id,
                idempotencyKey: 'u13-update-cross-room',
                roomId: $foreignRoom,
                roomIdProvided: true,
            ));
            $this->fail('Expected ExamValidationException for cross-school room update.');
        } catch (ExamValidationException $e) {
            $this->assertStringContainsString('current school', $e->getMessage());
        }

        $this->assertSame(
            $roomBefore,
            DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
                ->where('id', $graph['session_id'])
                ->value('room_id')
        );
        $this->assertSame($outboxBefore, $this->outboxCount(ExamSessionUpdated::class));
        $this->assertSame($gradesBefore, $this->gradeCount($schoolA));
        $this->assertSame($attendanceBefore, $this->attendanceCount($schoolA));
    }

    #[Test]
    public function update_denies_unresolvable_room_fail_closed(): void
    {
        $schoolId = $this->createSchool('SCH-72-R13I', 'U13 Update Missing Room');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'R13I');

        $this->expectException(ExamValidationException::class);
        $this->app->make(UpdateExamSessionHandler::class)->handle(new UpdateExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u13-update-missing-room',
            roomId: 9_999_999_002,
            roomIdProvided: true,
        ));
    }

    #[Test]
    public function update_without_room_id_preserves_existing_behavior(): void
    {
        $schoolId = $this->createSchool('SCH-72-R13J', 'U13 Update Omit Room');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'R13J');
        $existingRoom = $this->seedRoomForSchool($schoolId, 'R13J');

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['room_id' => $existingRoom]);

        $result = $this->app->make(UpdateExamSessionHandler::class)->handle(new UpdateExamSessionCommand(
            schoolId: $schoolId,
            examSessionId: $graph['session_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u13-update-omit-room',
            sessionDate: '2026-12-10',
        ));

        $this->assertTrue($result->success);
        $row = DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->first();
        $this->assertSame('2026-12-10', (string) $row->session_date);
        $this->assertSame($existingRoom, (int) $row->room_id);
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

    private function sessionCountForExam(int $examId): int
    {
        return (int) DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('exam_id', $examId)
            ->count();
    }

    private function outboxCount(string $eventType): int
    {
        return (int) DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
            ->where('event_type', $eventType)
            ->count();
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
