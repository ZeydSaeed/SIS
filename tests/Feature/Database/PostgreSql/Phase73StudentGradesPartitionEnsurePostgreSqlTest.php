<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Academic\Commands\CreateAcademicYearCommand;
use App\Application\Academic\Commands\CreateAcademicYearHandler;
use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Database\SchemaHelper;
use App\Database\StudentGradesPartitionManager;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

/**
 * Phase 7.3-U02 — academic year creation ensures student_grades LIST partition (no DEFAULT).
 */
final class Phase73StudentGradesPartitionEnsurePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function create_academic_year_handler_ensures_grades_partition(): void
    {
        $code = 'AY73'.substr(uniqid(), -8);
        $result = $this->app->make(CreateAcademicYearHandler::class)->handle(new CreateAcademicYearCommand(
            code: $code,
            name: 'Phase 73 U02 Year',
            startDate: '2026-09-01',
            endDate: '2027-06-30',
            isCurrent: false,
            status: 1,
            idempotencyKey: 'u02-create-'.$code,
        ));

        $this->assertTrue($result->success);
        $this->assertNotNull($result->academicYearId);
        $this->assertTrue(StudentGradesPartitionManager::partitionExists((int) $result->academicYearId));
        $this->assertNotSame(
            'student_grades_default',
            StudentGradesPartitionManager::partitionTableName((int) $result->academicYearId),
        );
    }

    #[Test]
    public function test_helper_create_academic_year_ensures_partition_and_grade_enter_works(): void
    {
        $schoolId = $this->createSchool('SCH-73-U02', 'U02 Partition School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $yearId = $this->createAcademicYear('AY-73-HELPER-U02');

        $this->assertTrue(StudentGradesPartitionManager::partitionExists($yearId));

        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U02H', yearId: $yearId);
        $this->markSessionInProgressForGradeEntry($graph['session_id']);

        $enter = $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '91',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'u02-enter-after-partition',
        ));

        $this->assertTrue($enter->success);
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'student_grades'), [
            'id' => $enter->gradeId,
            'academic_year_id' => $yearId,
        ]);
    }

    #[Test]
    public function ensure_partition_does_not_create_default_partition(): void
    {
        $yearId = $this->createAcademicYear('AY-73-NODEF');
        StudentGradesPartitionManager::ensurePartitionForAcademicYear($yearId);

        $default = DB::selectOne("
            SELECT 1 AS ok
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'exams' AND c.relname = 'student_grades_default'
        ");

        $this->assertNull($default);
    }
}
