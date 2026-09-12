<?php

namespace Tests\Feature\Database\PostgreSql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTrTransfersSchemaPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    #[Test]
    public function transfer_requests_table_exists_with_required_columns(): void
    {
        $cols = collect(DB::select("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = 'transfers' AND table_name = 'transfer_requests'
            ORDER BY ordinal_position
        "))->pluck('column_name')->all();

        foreach ([
            'id',
            'student_id',
            'from_school_id',
            'to_school_id',
            'from_enrollment_id',
            'academic_year_id',
            'reason',
            'status',
            'requested_by',
            'requested_at',
            'approved_by',
            'approved_at',
            'created_at',
        ] as $required) {
            $this->assertContains($required, $cols, "Missing column {$required}");
        }
    }

    #[Test]
    public function transfer_records_include_dual_school_columns(): void
    {
        $cols = collect(DB::select("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = 'transfers' AND table_name = 'transfer_records'
            ORDER BY ordinal_position
        "))->pluck('column_name')->all();

        foreach ([
            'id',
            'transfer_request_id',
            'student_id',
            'from_school_id',
            'to_school_id',
            'from_enrollment_id',
            'to_enrollment_id',
            'effective_date',
            'completed_at',
            'created_at',
        ] as $required) {
            $this->assertContains($required, $cols, "Missing column {$required}");
        }
    }

    #[Test]
    public function transfers_tables_have_force_rls(): void
    {
        foreach (['transfer_requests', 'transfer_records'] as $table) {
            $row = DB::selectOne("
                SELECT c.relrowsecurity AS rls_enabled, c.relforcerowsecurity AS rls_forced
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'transfers' AND c.relname = ?
            ", [$table]);

            $this->assertNotNull($row);
            $this->assertTrue((bool) $row->rls_enabled);
            $this->assertTrue((bool) $row->rls_forced);
        }
    }

    #[Test]
    public function transfers_reject_delete_triggers_exist(): void
    {
        foreach (['transfer_requests', 'transfer_records'] as $table) {
            $trigger = DB::selectOne("
                SELECT 1 AS ok
                FROM pg_trigger t
                JOIN pg_class c ON c.oid = t.tgrelid
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'transfers' AND c.relname = ?
                  AND t.tgname = ?
                  AND NOT t.tgisinternal
            ", [$table, "{$table}_reject_delete"]);

            $this->assertNotNull($trigger, "Missing reject-delete on {$table}");
        }
    }
}
