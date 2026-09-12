<?php

namespace Tests\Feature\Exams;

use App\Application\Exams\Commands\CorrectStudentGradeCommand;
use App\Application\Exams\Commands\CorrectStudentGradeHandler;
use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Application\Exams\Commands\PresentExamEnrollmentCommand;
use App\Application\Exams\Commands\PresentExamEnrollmentHandler;
use App\Database\SchemaHelper;
use App\Domain\Exams\Events\StudentGradeCorrected;
use App\Domain\Exams\Exceptions\ExamSessionUnavailableException;
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
 * U12 — Correct fail-closed on Cancelled session/exam (HD-7.2-008 / Gate 7.2-U12).
 */
final class CorrectStudentGradeCancellationGuardTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function correct_denied_when_session_cancelled(): void
    {
        $schoolId = $this->createSchool('SCH-72-U12-SC', 'U12 Session Cancelled');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U12SC');
        $this->markSessionInProgressForGradeEntry($graph['session_id']);

        $entered = $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '70',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'u12-enter-sc',
        ));

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::Cancelled->value]);

        $gradesBefore = $this->gradeCount($schoolId);
        $outboxBefore = DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
            ->where('event_type', StudentGradeCorrected::class)
            ->count();
        $attendanceBefore = $this->attendanceCount($schoolId);

        try {
            $this->app->make(CorrectStudentGradeHandler::class)->handle(new CorrectStudentGradeCommand(
                schoolId: $schoolId,
                gradeId: (int) $entered->gradeId,
                academicYearId: $graph['year_id'],
                score: '80',
                isAbsent: false,
                reason: 'should fail',
                correctedBy: (int) $user->id,
                idempotencyKey: 'u12-correct-sc',
            ));
            $this->fail('Expected Cancelled session deny');
        } catch (ExamSessionUnavailableException) {
            $this->assertTrue(true);
        }

        $this->assertSame($gradesBefore, $this->gradeCount($schoolId));
        $this->assertSame($outboxBefore, DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
            ->where('event_type', StudentGradeCorrected::class)
            ->count());
        $this->assertSame($attendanceBefore, $this->attendanceCount($schoolId));
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'student_grades'), [
            'id' => $entered->gradeId,
            'is_current' => true,
            'score' => 70,
        ]);
    }

    #[Test]
    public function correct_denied_when_exam_cancelled(): void
    {
        $schoolId = $this->createSchool('SCH-72-U12-EX', 'U12 Exam Cancelled');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U12EX');
        $this->markSessionInProgressForGradeEntry($graph['session_id']);

        $entered = $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '65',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'u12-enter-ex',
        ));

        DB::table(SchemaHelper::qualified('exams', 'exams'))
            ->where('id', $graph['exam_id'])
            ->update(['status' => ExamStatus::Cancelled->value]);

        $gradesBefore = $this->gradeCount($schoolId);

        $this->expectException(ExamSessionUnavailableException::class);
        try {
            $this->app->make(CorrectStudentGradeHandler::class)->handle(new CorrectStudentGradeCommand(
                schoolId: $schoolId,
                gradeId: (int) $entered->gradeId,
                academicYearId: $graph['year_id'],
                score: '75',
                isAbsent: false,
                reason: 'should fail exam cancelled',
                correctedBy: (int) $user->id,
                idempotencyKey: 'u12-correct-ex',
            ));
        } finally {
            $this->assertSame($gradesBefore, $this->gradeCount($schoolId));
            $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'student_grades'), [
                'id' => $entered->gradeId,
                'is_current' => true,
            ]);
        }
    }

    #[Test]
    public function correct_allowed_when_session_and_exam_not_cancelled(): void
    {
        $schoolId = $this->createSchool('SCH-72-U12-OK', 'U12 Correct OK');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U12OK');
        $this->markSessionInProgressForGradeEntry($graph['session_id']);

        $entered = $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '60',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'u12-enter-ok',
        ));

        $result = $this->app->make(CorrectStudentGradeHandler::class)->handle(new CorrectStudentGradeCommand(
            schoolId: $schoolId,
            gradeId: (int) $entered->gradeId,
            academicYearId: $graph['year_id'],
            score: '85',
            isAbsent: false,
            reason: 'marking error',
            correctedBy: (int) $user->id,
            idempotencyKey: 'u12-correct-ok',
        ));

        $this->assertTrue($result->success);
        $this->assertSame($entered->gradeId, $result->previousGradeId);
        $this->assertNotSame($entered->gradeId, $result->newGradeId);
        $this->assertTrue(
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', StudentGradeCorrected::class)
                ->where('payload', 'like', '%"previous_grade_id":'.$entered->gradeId.'%')
                ->exists()
        );
    }

    #[Test]
    public function u09_u11_regression_present_and_enter_timing_unchanged(): void
    {
        $schoolId = $this->createSchool('SCH-72-U12-REG', 'U12 Regression');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U12RG');
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
            idempotencyKey: 'u12-u09',
        ));
        $this->assertSame(ExamEnrollmentStatus::Present->value, $present->status);

        $enter = $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '77',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'u12-u11-enter',
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
