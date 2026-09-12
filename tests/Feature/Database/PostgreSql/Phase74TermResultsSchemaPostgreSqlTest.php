<?php

namespace Tests\Feature\Database\PostgreSql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * Phase 7.4-U01 — results.term_results schema + FORCE RLS (no calculator).
 */
final class Phase74TermResultsSchemaPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    #[Test]
    public function term_results_table_exists_with_required_columns(): void
    {
        $cols = collect(DB::select("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = 'results' AND table_name = 'term_results'
            ORDER BY ordinal_position
        "))->pluck('column_name')->all();

        foreach ([
            'id',
            'school_id',
            'enrollment_id',
            'student_id',
            'academic_year_id',
            'term_id',
            'subject_id',
            'result_version',
            'lifecycle_status',
            'is_official',
            'is_current_operational',
            'is_current_official',
            'weighted_total',
            'pass_fail',
            'incomplete',
            'source_fingerprint',
            'policy_pin',
            'calculated_at',
        ] as $required) {
            $this->assertContains($required, $cols, "Missing column {$required}");
        }

        $this->assertNotContains('grade_letter', $cols);
        $this->assertNotContains('gpa', $cols);
        $this->assertNotContains('rank_in_section', $cols);
        $this->assertNotContains('total_grade', $cols);
    }

    #[Test]
    public function term_results_rls_is_enabled_and_forced(): void
    {
        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls_enabled, c.relforcerowsecurity AS rls_forced
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'results' AND c.relname = 'term_results'
        ");

        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->rls_enabled);
        $this->assertTrue((bool) $row->rls_forced);
    }

    #[Test]
    public function term_results_reject_delete_trigger_exists(): void
    {
        $trigger = DB::selectOne("
            SELECT 1 AS ok
            FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'results' AND c.relname = 'term_results'
              AND t.tgname = 'term_results_reject_delete'
              AND NOT t.tgisinternal
        ");

        $this->assertNotNull($trigger);

        $fn = DB::selectOne("
            SELECT 1 AS ok
            FROM pg_proc p
            JOIN pg_namespace n ON n.oid = p.pronamespace
            WHERE n.nspname = 'results' AND p.proname = 'reject_term_results_delete'
        ");
        $this->assertNotNull($fn);
    }

    #[Test]
    public function student_grades_still_has_no_default_partition(): void
    {
        $default = DB::select("
            SELECT c.relname
            FROM pg_inherits i
            JOIN pg_class c ON c.oid = i.inhrelid
            JOIN pg_class p ON p.oid = i.inhparent
            JOIN pg_namespace n ON n.oid = p.relnamespace
            WHERE n.nspname = 'exams' AND p.relname = 'student_grades'
              AND pg_get_expr(c.relpartbound, c.oid) ILIKE '%DEFAULT%'
        ");

        $this->assertSame([], $default);
    }
}
