<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Exams\Commands\CancelExamEnrollmentCommand;
use App\Application\Exams\Commands\CancelExamEnrollmentHandler;
use App\Application\Exams\Commands\CancelExamSessionCommand;
use App\Application\Exams\Commands\CancelExamSessionHandler;
use App\Application\Exams\Commands\CreateExamEnrollmentCommand;
use App\Application\Exams\Commands\CreateExamEnrollmentHandler;
use App\Application\Exams\Commands\CreateExamSessionCommand;
use App\Application\Exams\Commands\CreateExamSessionHandler;
use App\Application\Exams\Commands\UpdateExamSessionCommand;
use App\Application\Exams\Commands\UpdateExamSessionHandler;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Models\User;
use App\Security\Context\SchoolContext;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;
use Tests\Support\Database\SeedsExamGradeGraph;

/**
 * Phase 7.2 Batch 6 U15 — DD-019 writer-path PostgreSQL RLS verification
 * for exam_sessions / exam_enrollments (verify only; no RLS mutation).
 */
final class Phase72SessionEnrollmentRlsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use SeedsExamGradeGraph;

    #[Test]
    public function force_rls_remains_enabled_on_session_and_enrollment_surfaces(): void
    {
        foreach (['exam_sessions', 'exam_enrollments'] as $table) {
            $row = DB::selectOne(
                'SELECT c.relrowsecurity AS enabled, c.relforcerowsecurity AS forced
                 FROM pg_class c
                 JOIN pg_namespace n ON n.oid = c.relnamespace
                 WHERE n.nspname = ? AND c.relname = ?',
                ['exams', $table],
            );

            $this->assertNotNull($row, "catalog row missing for exams.{$table}");
            $this->assertTrue((bool) $row->enabled, "exams.{$table} RLS enabled");
            $this->assertTrue((bool) $row->forced, "exams.{$table} FORCE RLS");
        }
    }

    #[Test]
    public function same_school_phase72_session_and_enrollment_writers_succeed_under_rls_actor(): void
    {
        $g = $this->seedTwoSchoolGradeGraph();
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantGradesManager($user, $g['school_a']);

        $examId = (int) DB::table('exams.exam_sessions')
            ->where('id', $g['session_a'])
            ->value('exam_id');
        $this->assertGreaterThan(0, $examId);

        $this->app->make(SchoolContext::class)->set($g['school_a']);
        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);

        $createdSession = $this->app->make(CreateExamSessionHandler::class)->handle(new CreateExamSessionCommand(
            schoolId: $g['school_a'],
            examId: $examId,
            subjectId: $g['subject_id'],
            sessionDate: '2026-12-01',
            startTime: '09:00:00',
            endTime: '11:00:00',
            actorUserId: (int) $user->id,
            idempotencyKey: 'u15-create-session',
        ));
        $this->assertTrue($createdSession->success);
        $this->assertNotNull($createdSession->examSessionId);

        $updated = $this->app->make(UpdateExamSessionHandler::class)->handle(new UpdateExamSessionCommand(
            schoolId: $g['school_a'],
            examSessionId: (int) $createdSession->examSessionId,
            actorUserId: (int) $user->id,
            idempotencyKey: 'u15-update-session',
            sessionDate: '2026-12-02',
        ));
        $this->assertTrue($updated->success);

        $createdEnrollment = $this->app->make(CreateExamEnrollmentHandler::class)->handle(new CreateExamEnrollmentCommand(
            schoolId: $g['school_a'],
            examSessionId: (int) $createdSession->examSessionId,
            enrollmentId: $g['enrollment_a'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u15-create-enrollment',
            seatNumber: 'U15-1',
        ));
        $this->assertTrue($createdEnrollment->success);
        $this->assertNotNull($createdEnrollment->examEnrollmentId);

        $cancelledEnrollment = $this->app->make(CancelExamEnrollmentHandler::class)->handle(new CancelExamEnrollmentCommand(
            schoolId: $g['school_a'],
            examSessionId: (int) $createdSession->examSessionId,
            examEnrollmentId: (int) $createdEnrollment->examEnrollmentId,
            enrollmentId: $g['enrollment_a'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'u15-cancel-enrollment',
        ));
        $this->assertTrue($cancelledEnrollment->success);
        $this->assertSame(ExamEnrollmentStatus::Withdrawn->value, $cancelledEnrollment->status);

        $cancelledSession = $this->app->make(CancelExamSessionHandler::class)->handle(new CancelExamSessionCommand(
            schoolId: $g['school_a'],
            examSessionId: (int) $createdSession->examSessionId,
            actorUserId: (int) $user->id,
            idempotencyKey: 'u15-cancel-session',
        ));
        $this->assertTrue($cancelledSession->success);
        $this->assertSame(ExamSessionStatus::Cancelled->value, $cancelledSession->status);

        PostgreSqlRlsActor::reset();
    }

    #[Test]
    public function cross_school_session_and_enrollment_mutation_denied_by_postgresql_rls(): void
    {
        $g = $this->seedTwoSchoolGradeGraph();

        $sessionDateBefore = (string) DB::table('exams.exam_sessions')
            ->where('id', $g['session_a'])
            ->value('session_date');
        $seatBefore = DB::table('exams.exam_enrollments')
            ->where('id', $g['exam_enrollment_a'])
            ->value('seat_number');

        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_b']]);

        $this->assertSame(0, (int) DB::selectOne(
            'SELECT COUNT(*) AS c FROM exams.exam_sessions WHERE id = ?',
            [$g['session_a']],
        )->c);
        $this->assertSame(0, (int) DB::selectOne(
            'SELECT COUNT(*) AS c FROM exams.exam_enrollments WHERE id = ?',
            [$g['exam_enrollment_a']],
        )->c);

        $updatedSessions = DB::update(
            'UPDATE exams.exam_sessions SET session_date = ? WHERE id = ?',
            ['2099-01-01', $g['session_a']],
        );
        $updatedEnrollments = DB::update(
            'UPDATE exams.exam_enrollments SET seat_number = ? WHERE id = ?',
            ['HIJACK', $g['exam_enrollment_a']],
        );
        $this->assertSame(0, $updatedSessions);
        $this->assertSame(0, $updatedEnrollments);

        PostgreSqlRlsActor::reset();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);

        $this->assertSame(
            $sessionDateBefore,
            (string) DB::table('exams.exam_sessions')->where('id', $g['session_a'])->value('session_date'),
        );
        $this->assertSame(
            $seatBefore,
            DB::table('exams.exam_enrollments')->where('id', $g['exam_enrollment_a'])->value('seat_number'),
        );
    }

    #[Test]
    public function missing_school_context_fails_closed_on_session_and_enrollment_surfaces(): void
    {
        $g = $this->seedTwoSchoolGradeGraph();
        $examId = (int) DB::table('exams.exam_sessions')
            ->where('id', $g['session_a'])
            ->value('exam_id');

        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', '', true)");

        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM exams.exam_sessions')->c);
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM exams.exam_enrollments')->c);

        $sessionInsertFailed = false;
        DB::statement('SAVEPOINT u15_missing_guc_session');
        try {
            DB::table('exams.exam_sessions')->insert([
                'exam_id' => $examId,
                'school_id' => $g['school_a'],
                'subject_id' => $g['subject_id'],
                'session_date' => '2026-12-10',
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'max_grade' => 100,
                'pass_grade' => 50,
                'status' => ExamSessionStatus::Scheduled->value,
                'created_at' => now(),
            ]);
            DB::statement('ROLLBACK TO SAVEPOINT u15_missing_guc_session');
        } catch (\Throwable) {
            DB::statement('ROLLBACK TO SAVEPOINT u15_missing_guc_session');
            $sessionInsertFailed = true;
        }
        $this->assertTrue($sessionInsertFailed, 'exam_sessions INSERT without GUC must fail closed');

        $enrollmentInsertFailed = false;
        DB::statement('SAVEPOINT u15_missing_guc_enrollment');
        try {
            DB::table('exams.exam_enrollments')->insert([
                'exam_session_id' => $g['session_a'],
                'school_id' => $g['school_a'],
                'enrollment_id' => $g['enrollment_a'],
                'seat_number' => 'FAIL',
                'status' => ExamEnrollmentStatus::Registered->value,
                'created_at' => now(),
            ]);
            DB::statement('ROLLBACK TO SAVEPOINT u15_missing_guc_enrollment');
        } catch (\Throwable) {
            DB::statement('ROLLBACK TO SAVEPOINT u15_missing_guc_enrollment');
            $enrollmentInsertFailed = true;
        }
        $this->assertTrue($enrollmentInsertFailed, 'exam_enrollments INSERT without GUC must fail closed');

        PostgreSqlRlsActor::reset();
    }

    #[Test]
    public function cross_school_phase72_handler_path_cannot_mutate_foreign_session_under_rls(): void
    {
        $g = $this->seedTwoSchoolGradeGraph();
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantGradesManager($user, $g['school_b']);

        $sessionDateBefore = (string) DB::table('exams.exam_sessions')
            ->where('id', $g['session_a'])
            ->value('session_date');

        $this->app->make(SchoolContext::class)->set($g['school_b']);
        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_b']]);

        $denied = false;
        try {
            $this->app->make(UpdateExamSessionHandler::class)->handle(new UpdateExamSessionCommand(
                schoolId: $g['school_a'],
                examSessionId: $g['session_a'],
                actorUserId: (int) $user->id,
                idempotencyKey: 'u15-cross-update-session',
                sessionDate: '2099-02-02',
            ));
        } catch (\Throwable) {
            $denied = true;
        }
        $this->assertTrue($denied);

        $cancelDenied = false;
        try {
            $this->app->make(CancelExamSessionHandler::class)->handle(new CancelExamSessionCommand(
                schoolId: $g['school_a'],
                examSessionId: $g['session_a'],
                actorUserId: (int) $user->id,
                idempotencyKey: 'u15-cross-cancel-session',
            ));
        } catch (\Throwable) {
            $cancelDenied = true;
        }
        $this->assertTrue($cancelDenied);

        PostgreSqlRlsActor::reset();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);
        $this->assertSame(
            $sessionDateBefore,
            (string) DB::table('exams.exam_sessions')->where('id', $g['session_a'])->value('session_date'),
        );
        $this->assertSame(
            ExamSessionStatus::Scheduled->value,
            (int) DB::table('exams.exam_sessions')->where('id', $g['session_a'])->value('status'),
        );
    }
}
