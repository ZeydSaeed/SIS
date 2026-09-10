<?php

namespace Tests\Feature\Database;

use App\Database\SchemaHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Phase3BStudentGradesSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function student_grades_table_exists_with_expected_columns(): void
    {
        $table = SchemaHelper::qualified('exams', 'student_grades');

        $this->assertTrue(Schema::hasTable($table));
        $this->assertTrue(Schema::hasColumns($table, [
            'id', 'academic_year_id', 'school_id', 'exam_enrollment_id', 'exam_session_id',
            'enrollment_id', 'student_id', 'subject_id', 'score', 'max_score', 'is_absent',
            'status', 'is_current', 'correction_of_grade_id', 'entered_by', 'entered_at',
            'finalized_at', 'created_at', 'updated_at',
        ]));
    }

    #[Test]
    public function phase_3b_does_not_create_derived_result_tables(): void
    {
        $this->assertFalse(Schema::hasTable(SchemaHelper::qualified('results', 'term_results')));
        $this->assertFalse(Schema::hasTable(SchemaHelper::qualified('results', 'annual_results')));
        $this->assertFalse(Schema::hasTable(SchemaHelper::qualified('results', 'transcripts')));
    }

    #[Test]
    public function exam_enrollments_still_have_no_score_columns(): void
    {
        $columns = Schema::getColumnListing(SchemaHelper::qualified('exams', 'exam_enrollments'));
        $this->assertNotContains('score', $columns);
        $this->assertNotContains('max_score', $columns);
    }

    #[Test]
    public function postgresql_partition_rls_and_checks_exist(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            $this->markTestSkipped('PostgreSQL required for partition/RLS catalog assertions.');
        }

        $parted = DB::selectOne("
            SELECT c.relkind, c.relrowsecurity, c.relforcerowsecurity
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'exams' AND c.relname = 'student_grades'
        ");
        $this->assertSame('p', $parted->relkind);
        $this->assertTrue((bool) $parted->relrowsecurity);
        $this->assertTrue((bool) $parted->relforcerowsecurity);

        $default = DB::selectOne("
            SELECT COUNT(*) AS c
            FROM pg_inherits i
            JOIN pg_class child ON child.oid = i.inhrelid
            JOIN pg_class parent ON parent.oid = i.inhparent
            JOIN pg_namespace n ON n.oid = parent.relnamespace
            WHERE n.nspname = 'exams'
              AND parent.relname = 'student_grades'
              AND pg_get_expr(child.relpartbound, child.oid) = 'DEFAULT'
        ");
        $this->assertSame(0, (int) $default->c);

        $checks = collect(DB::select("
            SELECT con.conname
            FROM pg_constraint con
            JOIN pg_class rel ON rel.oid = con.conrelid
            JOIN pg_namespace nsp ON nsp.oid = rel.relnamespace
            WHERE nsp.nspname = 'exams' AND rel.relname = 'student_grades' AND con.contype = 'c'
        "))->pluck('conname');

        $this->assertTrue($checks->contains('student_grades_score_absent_check'));
        $this->assertTrue($checks->contains('student_grades_max_score_check'));
        $this->assertTrue($checks->contains('student_grades_status_check'));
        $this->assertTrue($checks->contains('student_grades_void_not_current_check'));
        $this->assertTrue($checks->contains('student_grades_no_self_correction_check'));

        $policies = collect(DB::select("
            SELECT policyname FROM pg_policies
            WHERE schemaname = 'exams' AND tablename = 'student_grades'
        "))->pluck('policyname');
        $this->assertTrue($policies->contains('student_grades_school_isolation'));
    }
}
