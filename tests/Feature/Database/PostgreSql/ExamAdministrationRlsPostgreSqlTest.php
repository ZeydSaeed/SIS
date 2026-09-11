<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Exams\Commands\CancelExamCommand;
use App\Application\Exams\Commands\CancelExamHandler;
use App\Application\Exams\Commands\CreateExamCommand;
use App\Application\Exams\Commands\CreateExamHandler;
use App\Application\Exams\Commands\UpdateExamCommand;
use App\Application\Exams\Commands\UpdateExamHandler;
use App\Domain\Exams\Data\CreateExamData;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Domain\Exams\ValueObjects\ExamStatus;
use App\Models\User;
use App\Security\Context\SchoolContext;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;
use Tests\Support\Database\SeedsExamGradeGraph;

/**
 * Phase 7.1 — genuine PostgreSQL RLS runtime verification for exam writers.
 * Uses non-superuser sis_rls_tester (superusers bypass RLS).
 */
final class ExamAdministrationRlsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use SeedsExamGradeGraph;

    #[Test]
    public function force_rls_remains_enabled_on_exam_surfaces(): void
    {
        foreach (['exams', 'exam_sessions', 'exam_enrollments', 'student_grades'] as $table) {
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
    public function same_school_create_exam_succeeds_under_rls_actor(): void
    {
        $g = $this->seedTwoSchoolGradeGraph();
        $repo = $this->app->make(ExamRepositoryInterface::class);

        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);

        $examId = $repo->insert(new CreateExamData(
            schoolId: $g['school_a'],
            academicYearId: $g['year_a'],
            termId: $g['term_a'],
            examTypeId: $g['type_id'],
            name: 'RLS Same School Create',
            startDate: '2026-11-01',
            endDate: '2026-11-15',
            status: ExamStatus::Draft->value,
        ));

        $found = $repo->findByIdAndSchool($examId, $g['school_a']);
        $this->assertNotNull($found);
        $this->assertSame('RLS Same School Create', $found->name);
        $this->assertSame($g['school_a'], $found->schoolId);

        PostgreSqlRlsActor::reset();
    }

    #[Test]
    public function cross_school_mutation_is_denied_by_postgresql_rls(): void
    {
        $g = $this->seedTwoSchoolGradeGraph();

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);
        $examId = (int) DB::table('exams.exams')->insertGetId([
            'academic_year_id' => $g['year_a'],
            'school_id' => $g['school_a'],
            'term_id' => $g['term_a'],
            'exam_type_id' => $g['type_id'],
            'name' => 'School A Exam',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-15',
            'status' => ExamStatus::Draft->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $repo = $this->app->make(ExamRepositoryInterface::class);

        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_b']]);

        $this->assertNull($repo->findByIdAndSchool($examId, $g['school_a']));
        $this->assertNull($repo->findByIdAndSchool($examId, $g['school_b']));

        $repo->updateAllowlisted($examId, $g['school_a'], ['name' => 'Hijacked']);
        $repo->updateAllowlisted($examId, $g['school_b'], ['name' => 'Hijacked B']);

        PostgreSqlRlsActor::reset();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);

        $row = DB::table('exams.exams')->where('id', $examId)->first();
        $this->assertNotNull($row);
        $this->assertSame('School A Exam', (string) $row->name);
        $this->assertSame($g['school_a'], (int) $row->school_id);
    }

    #[Test]
    public function missing_school_context_fails_closed_on_exam_surfaces(): void
    {
        $g = $this->seedTwoSchoolGradeGraph();

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);
        $examId = (int) DB::table('exams.exams')->where('school_id', $g['school_a'])->value('id');
        $this->assertNotNull($examId);

        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', '', true)");

        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM exams.exams')->c);
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM exams.exam_sessions')->c);
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM exams.exam_enrollments')->c);
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM exams.student_grades')->c);

        $insertFailed = false;
        DB::statement('SAVEPOINT rls_missing_context_insert');
        try {
            DB::table('exams.exams')->insert([
                'academic_year_id' => $g['year_a'],
                'school_id' => $g['school_a'],
                'term_id' => $g['term_a'],
                'exam_type_id' => $g['type_id'],
                'name' => 'Should Fail Closed',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-15',
                'status' => ExamStatus::Draft->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::statement('ROLLBACK TO SAVEPOINT rls_missing_context_insert');
        } catch (\Throwable) {
            DB::statement('ROLLBACK TO SAVEPOINT rls_missing_context_insert');
            $insertFailed = true;
        }
        $this->assertTrue($insertFailed, 'INSERT without app.current_school_id must fail closed under RLS');

        PostgreSqlRlsActor::reset();
    }

    #[Test]
    public function create_update_cancel_handlers_respect_postgresql_rls_same_school(): void
    {
        $g = $this->seedTwoSchoolGradeGraph();
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantGradesManager($user, $g['school_a']);

        $this->app->make(SchoolContext::class)->set($g['school_a']);

        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);

        $created = $this->app->make(CreateExamHandler::class)->handle(new CreateExamCommand(
            schoolId: $g['school_a'],
            academicYearId: $g['year_a'],
            termId: $g['term_a'],
            examTypeId: $g['type_id'],
            name: 'Handler RLS Exam',
            startDate: '2026-11-01',
            endDate: '2026-11-15',
            actorUserId: (int) $user->id,
            idempotencyKey: 'rls-create-1',
        ));
        $this->assertTrue($created->success);
        $this->assertNotNull($created->examId);

        $updated = $this->app->make(UpdateExamHandler::class)->handle(new UpdateExamCommand(
            schoolId: $g['school_a'],
            examId: (int) $created->examId,
            actorUserId: (int) $user->id,
            idempotencyKey: 'rls-update-1',
            name: 'Handler RLS Exam Updated',
        ));
        $this->assertTrue($updated->success);

        $sessionId = (int) DB::table('exams.exam_sessions')->insertGetId([
            'exam_id' => $created->examId,
            'school_id' => $g['school_a'],
            'subject_id' => $g['subject_id'],
            'session_date' => '2026-11-05',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'max_grade' => 100,
            'pass_grade' => 50,
            'status' => ExamSessionStatus::Scheduled->value,
            'created_at' => now(),
        ]);
        $enrollmentId = (int) DB::table('exams.exam_enrollments')->insertGetId([
            'exam_session_id' => $sessionId,
            'school_id' => $g['school_a'],
            'enrollment_id' => $g['enrollment_a'],
            'seat_number' => 'R1',
            'status' => ExamEnrollmentStatus::Registered->value,
            'created_at' => now(),
        ]);

        $cancelled = $this->app->make(CancelExamHandler::class)->handle(new CancelExamCommand(
            schoolId: $g['school_a'],
            examId: (int) $created->examId,
            actorUserId: (int) $user->id,
            idempotencyKey: 'rls-cancel-1',
        ));
        $this->assertTrue($cancelled->success);
        $this->assertSame(ExamStatus::Cancelled->value, $cancelled->status);
        $this->assertContains($sessionId, $cancelled->cancelledSessionIds);
        $this->assertContains($enrollmentId, $cancelled->withdrawnEnrollmentIds);

        PostgreSqlRlsActor::reset();
    }

    #[Test]
    public function cross_school_handler_path_cannot_mutate_foreign_exam_under_rls(): void
    {
        $g = $this->seedTwoSchoolGradeGraph();
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantGradesManager($user, $g['school_b']);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);
        $examId = (int) DB::table('exams.exams')->insertGetId([
            'academic_year_id' => $g['year_a'],
            'school_id' => $g['school_a'],
            'term_id' => $g['term_a'],
            'exam_type_id' => $g['type_id'],
            'name' => 'Foreign Exam',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-15',
            'status' => ExamStatus::Draft->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Actor authorized for school B only; GUC = school B. RLS hides school A rows.
        $this->app->make(SchoolContext::class)->set($g['school_b']);
        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_b']]);

        $deniedByAuthority = false;
        try {
            $this->app->make(UpdateExamHandler::class)->handle(new UpdateExamCommand(
                schoolId: $g['school_a'],
                examId: $examId,
                actorUserId: (int) $user->id,
                idempotencyKey: 'rls-cross-update',
                name: 'Should Not Apply',
            ));
        } catch (\Throwable) {
            $deniedByAuthority = true;
        }
        $this->assertTrue($deniedByAuthority);

        // Even if school_id in command matched B but targeted A id: still invisible under RLS.
        $repo = $this->app->make(ExamRepositoryInterface::class);
        $this->assertNull($repo->lockByIdAndSchool($examId, $g['school_b']));

        PostgreSqlRlsActor::reset();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);
        $this->assertSame('Foreign Exam', (string) DB::table('exams.exams')->where('id', $examId)->value('name'));
    }
}
