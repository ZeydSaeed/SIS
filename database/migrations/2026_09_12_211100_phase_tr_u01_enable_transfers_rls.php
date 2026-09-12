<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase TR-U01 — Dual-school FORCE RLS + reject hard DELETE on transfers.*.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        $this->secureTable('transfer_requests');
        $this->secureTable('transfer_records');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        foreach (['transfer_requests', 'transfer_records'] as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_reject_delete ON transfers.{$table}");
            DB::statement("DROP FUNCTION IF EXISTS transfers.reject_{$table}_delete()");
            DB::statement("DROP POLICY IF EXISTS {$table}_dual_school_isolation ON transfers.{$table}");
            DB::statement("ALTER TABLE transfers.{$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE transfers.{$table} DISABLE ROW LEVEL SECURITY");
        }
    }

    private function secureTable(string $table): void
    {
        DB::statement("ALTER TABLE transfers.{$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE transfers.{$table} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS {$table}_dual_school_isolation ON transfers.{$table}");
        DB::statement("
            CREATE POLICY {$table}_dual_school_isolation ON transfers.{$table}
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND (
                    from_school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                    OR to_school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND (
                    from_school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                    OR to_school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            )
        ");

        DB::statement("
            CREATE OR REPLACE FUNCTION transfers.reject_{$table}_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of transfers.{$table} is forbidden';
            END;
            $$
        ");
        DB::statement("DROP TRIGGER IF EXISTS {$table}_reject_delete ON transfers.{$table}");
        DB::statement("
            CREATE TRIGGER {$table}_reject_delete
            BEFORE DELETE ON transfers.{$table}
            FOR EACH ROW
            EXECUTE FUNCTION transfers.reject_{$table}_delete()
        ");
    }
};
