<?php

namespace Tests\Feature\Exams;

use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Application\Exams\Commands\PresentExamEnrollmentCommand;
use App\Application\Exams\Commands\PresentExamEnrollmentHandler;
use App\Database\SchemaHelper;
use App\Domain\Exams\Events\StudentGradeEntered;
use App\Domain\Exams\Exceptions\ExamSessionUnavailableException;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Security\Context\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;
use Tests\TestCase;

/**
 * U11 — Grade entry timing (HD-7.2-009 / Gate 7.2-U11).
 */
final class EnterStudentGradeTimingPolicyTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function enter_allowed_when_session_in_progress(): void
    {
        $schoolId = $this->createSchool('SCH-72-U11-IP', 'U11 InProgress');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U11IP');
        $this->markSessionInProgressForGradeEntry($graph['session_id']);

        $gradesBefore = $this->gradeCount($schoolId);
        $attendanceBefore = $this->attendanceCount($schoolId);

        $result = $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '88',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'u11-ip',
        ));

        $this->assertTrue($result->success);
        $this->assertSame($gradesBefore + 1, $this->gradeCount($schoolId));
        $this->assertSame($attendanceBefore, $this->attendanceCount($schoolId));
        $this->assertTrue(
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', StudentGradeEntered::class)
                ->where('payload', 'like', '%"exam_enrollment_id":'.$graph['exam_enrollment_id'].'%')
                ->exists()
        );
    }

    #[Test]
    public function enter_allowed_when_session_completed(): void
    {
        $schoolId = $this->createSchool('SCH-72-U11-CP', 'U11 Completed');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U11CP');
        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::Completed->value]);

        $result = $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '90',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'u11-cp',
        ));

        $this->assertTrue($result->success);
    }

    #[Test]
    public function enter_denied_when_session_scheduled(): void
    {
        $schoolId = $this->createSchool('SCH-72-U11-SC', 'U11 Scheduled');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U11SC');
        // seed default = Scheduled

        $outboxBefore = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count();
        $gradesBefore = $this->gradeCount($schoolId);

        try {
            $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
                schoolId: $schoolId,
                examEnrollmentId: $graph['exam_enrollment_id'],
                score: '70',
                isAbsent: false,
                enteredBy: (int) $user->id,
                idempotencyKey: 'u11-sched',
            ));
            $this->fail('Expected Scheduled deny');
        } catch (ExamSessionUnavailableException $e) {
            $this->assertStringContainsString('not eligible for grade entry', $e->getMessage());
        }

        $this->assertSame($gradesBefore, $this->gradeCount($schoolId));
        $this->assertSame($outboxBefore, DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))->count());
    }

    #[Test]
    public function enter_denied_when_session_cancelled(): void
    {
        $schoolId = $this->createSchool('SCH-72-U11-CA', 'U11 Cancelled');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U11CA');
        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::Cancelled->value]);

        $gradesBefore = $this->gradeCount($schoolId);

        $this->expectException(ExamSessionUnavailableException::class);
        try {
            $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
                schoolId: $schoolId,
                examEnrollmentId: $graph['exam_enrollment_id'],
                score: '70',
                isAbsent: false,
                enteredBy: (int) $user->id,
                idempotencyKey: 'u11-canc',
            ));
        } finally {
            $this->assertSame($gradesBefore, $this->gradeCount($schoolId));
        }
    }

    #[Test]
    public function enter_idempotent_replay_on_in_progress(): void
    {
        $schoolId = $this->createSchool('SCH-72-U11-ID', 'U11 Idemp');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U11ID');
        $this->markSessionInProgressForGradeEntry($graph['session_id']);
        $handler = $this->app->make(EnterStudentGradeHandler::class);

        $first = $handler->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '66',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'u11-idemp',
        ));
        $replay = $handler->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '66',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'u11-idemp',
        ));

        $this->assertTrue($replay->fromIdempotencyCache);
        $this->assertSame($first->gradeId, $replay->gradeId);
        $this->assertSame(
            1,
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', StudentGradeEntered::class)
                ->where('payload', 'like', '%"exam_enrollment_id":'.$graph['exam_enrollment_id'].'%')
                ->count()
        );
    }

    #[Test]
    public function u09_present_still_independent_of_enter_timing(): void
    {
        $schoolId = $this->createSchool('SCH-72-U11-U09', 'U11 U09 Regression');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U11U09');
        $this->markSessionInProgressForGradeEntry($graph['session_id']);
        DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
            ->where('id', $graph['exam_enrollment_id'])
            ->update(['status' => ExamEnrollmentStatus::Confirmed->value]);

        $present = $this->app->make(PresentExamEnrollmentHandler::class)->handle(new PresentExamEnrollmentCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            examSessionId: $graph['session_id'],
            enrollmentId: $graph['enrollment_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u11-u09',
        ));
        $this->assertSame(ExamEnrollmentStatus::Present->value, $present->status);

        $enter = $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '80',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'u11-u09-enter',
        ));
        $this->assertTrue($enter->success);
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
