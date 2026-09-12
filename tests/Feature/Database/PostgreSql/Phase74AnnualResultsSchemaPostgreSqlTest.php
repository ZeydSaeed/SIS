<?php

namespace Tests\Feature\Database\PostgreSql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase74AnnualResultsSchemaPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    #[Test]
    public function annual_results_table_exists_without_gpa_or_rank_columns(): void
    {
        $cols = collect(DB::select("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = 'results' AND table_name = 'annual_results'
            ORDER BY ordinal_position
        "))->pluck('column_name')->all();

        foreach ([
            'id', 'school_id', 'enrollment_id', 'student_id', 'academic_year_id',
            'result_version', 'lifecycle_status', 'subjects_counted',
            'average_weighted_total', 'source_fingerprint', 'policy_pin',
        ] as $required) {
            $this->assertContains($required, $cols, "Missing {$required}");
        }

        $this->assertNotContains('gpa', $cols);
        $this->assertNotContains('rank_in_section', $cols);
        $this->assertNotContains('rank_in_class', $cols);
        $this->assertNotContains('total_credits', $cols);
    }

    #[Test]
    public function annual_results_rls_is_forced(): void
    {
        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls_enabled, c.relforcerowsecurity AS rls_forced
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'results' AND c.relname = 'annual_results'
        ");

        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->rls_enabled);
        $this->assertTrue((bool) $row->rls_forced);
    }

    #[Test]
    public function annual_results_reject_delete_trigger_exists(): void
    {
        $trigger = DB::selectOne("
            SELECT 1 AS ok
            FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'results' AND c.relname = 'annual_results'
              AND t.tgname = 'annual_results_reject_delete'
              AND NOT t.tgisinternal
        ");
        $this->assertNotNull($trigger);
    }
}
