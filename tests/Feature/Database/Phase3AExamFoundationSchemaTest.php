<?php

namespace Tests\Feature\Database;

use App\Database\SchemaHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Phase3AExamFoundationSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function exam_foundation_tables_exist_with_expected_columns(): void
    {
        $types = SchemaHelper::qualified('exams', 'exam_types');
        $exams = SchemaHelper::qualified('exams', 'exams');
        $sessions = SchemaHelper::qualified('exams', 'exam_sessions');
        $enrollments = SchemaHelper::qualified('exams', 'exam_enrollments');

        $this->assertTrue(Schema::hasTable($types));
        $this->assertTrue(Schema::hasTable($exams));
        $this->assertTrue(Schema::hasTable($sessions));
        $this->assertTrue(Schema::hasTable($enrollments));

        $this->assertTrue(Schema::hasColumns($types, [
            'id', 'code', 'name', 'weight_percentage',
        ]));
        $this->assertTrue(Schema::hasColumns($exams, [
            'id', 'academic_year_id', 'school_id', 'term_id', 'exam_type_id',
            'name', 'start_date', 'end_date', 'status', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns($sessions, [
            'id', 'exam_id', 'school_id', 'subject_id', 'session_date',
            'start_time', 'end_time', 'room_id', 'max_grade', 'pass_grade',
            'status', 'created_at',
        ]));
        $this->assertTrue(Schema::hasColumns($enrollments, [
            'id', 'exam_session_id', 'school_id', 'enrollment_id',
            'seat_number', 'status', 'created_at',
        ]));
    }

    #[Test]
    public function phase_3a_and_3b_do_not_create_phase_3c_result_tables(): void
    {
        // Phase 3B creates exams.student_grades; Phase 3C results remain deferred.
        $this->assertTrue(Schema::hasTable(SchemaHelper::qualified('exams', 'student_grades')));
        $this->assertFalse(Schema::hasTable(SchemaHelper::qualified('results', 'term_results')));
        $this->assertFalse(Schema::hasTable(SchemaHelper::qualified('results', 'annual_results')));
        $this->assertFalse(Schema::hasTable(SchemaHelper::qualified('results', 'transcripts')));
    }

    #[Test]
    public function exam_enrollments_do_not_store_scores(): void
    {
        $columns = Schema::getColumnListing(SchemaHelper::qualified('exams', 'exam_enrollments'));

        $this->assertNotContains('score', $columns);
        $this->assertNotContains('percentage', $columns);
        $this->assertNotContains('letter_grade', $columns);
        $this->assertNotContains('grade_points', $columns);
        $this->assertNotContains('grade', $columns);
    }

    #[Test]
    public function exams_schema_is_registered_in_schema_helper(): void
    {
        $this->assertContains('exams', SchemaHelper::schemas());
    }

    #[Test]
    public function postgresql_checks_and_rls_exist_on_school_scoped_exam_tables(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            $this->markTestSkipped('PostgreSQL required for CHECK/RLS catalog assertions.');
        }

        $checks = collect(DB::select("
            SELECT con.conname
            FROM pg_constraint con
            JOIN pg_class rel ON rel.oid = con.conrelid
            JOIN pg_namespace nsp ON nsp.oid = rel.relnamespace
            WHERE nsp.nspname = 'exams' AND con.contype = 'c'
        "))->pluck('conname');

        $this->assertTrue($checks->contains('exam_types_weight_percentage_check'));
        $this->assertTrue($checks->contains('exams_date_range_check'));
        $this->assertTrue($checks->contains('exams_status_check'));
        $this->assertTrue($checks->contains('exam_sessions_time_range_check'));
        $this->assertTrue($checks->contains('exam_sessions_pass_max_grade_check'));
        $this->assertTrue($checks->contains('exam_sessions_status_check'));
        $this->assertTrue($checks->contains('exam_enrollments_status_check'));

        foreach (['exams', 'exam_sessions', 'exam_enrollments'] as $table) {
            $rls = DB::selectOne("
                SELECT c.relrowsecurity AS enabled, c.relforcerowsecurity AS forced
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'exams' AND c.relname = ?
            ", [$table]);

            $this->assertTrue((bool) $rls->enabled, "{$table} RLS enabled");
            $this->assertTrue((bool) $rls->forced, "{$table} FORCE RLS");
        }

        $typesRls = DB::selectOne("
            SELECT c.relrowsecurity AS enabled
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'exams' AND c.relname = 'exam_types'
        ");
        $this->assertFalse((bool) $typesRls->enabled, 'exam_types remains global (no RLS)');

        $policies = collect(DB::select("
            SELECT policyname FROM pg_policies WHERE schemaname = 'exams'
        "))->pluck('policyname');

        $this->assertTrue($policies->contains('exams_school_isolation'));
        $this->assertTrue($policies->contains('exam_sessions_school_isolation'));
        $this->assertTrue($policies->contains('exam_enrollments_school_isolation'));
    }
}
