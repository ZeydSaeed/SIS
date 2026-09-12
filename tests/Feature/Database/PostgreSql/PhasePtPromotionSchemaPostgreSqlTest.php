<?php

namespace Tests\Feature\Database\PostgreSql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * Phase PT-U01 — promotion.rules + promotion.records schema + FORCE RLS.
 */
final class PhasePtPromotionSchemaPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    #[Test]
    public function promotion_rules_table_exists_with_required_columns(): void
    {
        $cols = collect(DB::select("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = 'promotion' AND table_name = 'rules'
            ORDER BY ordinal_position
        "))->pluck('column_name')->all();

        foreach ([
            'id',
            'school_id',
            'from_grade_level_id',
            'to_grade_level_id',
            'min_gpa',
            'min_pass_subjects',
            'max_failed_subjects',
            'is_active',
            'created_at',
        ] as $required) {
            $this->assertContains($required, $cols, "Missing column {$required}");
        }
    }

    #[Test]
    public function promotion_records_table_exists_with_school_id(): void
    {
        $cols = collect(DB::select("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = 'promotion' AND table_name = 'records'
            ORDER BY ordinal_position
        "))->pluck('column_name')->all();

        foreach ([
            'id',
            'school_id',
            'enrollment_id',
            'academic_year_id',
            'from_grade_level_id',
            'to_grade_level_id',
            'promotion_status',
            'gpa_at_promotion',
            'decided_by',
            'decided_at',
            'notes',
            'created_at',
        ] as $required) {
            $this->assertContains($required, $cols, "Missing column {$required}");
        }
    }

    #[Test]
    public function promotion_tables_have_force_rls(): void
    {
        foreach (['rules', 'records'] as $table) {
            $row = DB::selectOne("
                SELECT c.relrowsecurity AS rls_enabled, c.relforcerowsecurity AS rls_forced
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'promotion' AND c.relname = ?
            ", [$table]);

            $this->assertNotNull($row, "Missing table promotion.{$table}");
            $this->assertTrue((bool) $row->rls_enabled, "RLS not enabled on {$table}");
            $this->assertTrue((bool) $row->rls_forced, "RLS not forced on {$table}");
        }
    }

    #[Test]
    public function promotion_reject_delete_triggers_exist(): void
    {
        foreach (['rules', 'records'] as $table) {
            $trigger = DB::selectOne("
                SELECT 1 AS ok
                FROM pg_trigger t
                JOIN pg_class c ON c.oid = t.tgrelid
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'promotion' AND c.relname = ?
                  AND t.tgname = ?
                  AND NOT t.tgisinternal
            ", [$table, "{$table}_reject_delete"]);

            $this->assertNotNull($trigger, "Missing reject-delete trigger on {$table}");
        }
    }

    #[Test]
    public function transfers_tables_remain_absent(): void
    {
        $count = (int) DB::selectOne("
            SELECT COUNT(*)::int AS c
            FROM information_schema.tables
            WHERE table_schema = 'transfers'
        ")->c;

        $this->assertSame(0, $count);
    }
}
