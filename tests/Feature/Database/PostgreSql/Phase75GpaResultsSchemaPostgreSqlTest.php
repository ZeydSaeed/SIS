<?php

namespace Tests\Feature\Database\PostgreSql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase75GpaResultsSchemaPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    #[Test]
    public function gpa_results_table_exists_with_percent_scale_only(): void
    {
        $cols = collect(DB::select("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = 'results' AND table_name = 'gpa_results'
            ORDER BY ordinal_position
        "))->pluck('column_name')->all();

        foreach ([
            'id', 'school_id', 'enrollment_id', 'academic_year_id', 'gpa_scope',
            'gpa_value', 'scale_code', 'source_annual_result_id', 'source_fingerprint',
        ] as $required) {
            $this->assertContains($required, $cols, "Missing {$required}");
        }

        $this->assertNotContains('grade_letter', $cols);
        $this->assertNotContains('credit_hours', $cols);
    }

    #[Test]
    public function gpa_results_rls_is_forced(): void
    {
        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls_enabled, c.relforcerowsecurity AS rls_forced
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'results' AND c.relname = 'gpa_results'
        ");

        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->rls_enabled);
        $this->assertTrue((bool) $row->rls_forced);
    }

    #[Test]
    public function gpa_results_reject_delete_trigger_exists(): void
    {
        $trigger = DB::selectOne("
            SELECT 1 AS ok
            FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'results' AND c.relname = 'gpa_results'
              AND t.tgname = 'gpa_results_reject_delete'
              AND NOT t.tgisinternal
        ");
        $this->assertNotNull($trigger);
    }
}
