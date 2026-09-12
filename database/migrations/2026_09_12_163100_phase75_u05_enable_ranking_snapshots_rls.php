<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 7.5-U05 — FORCE RLS on ranking snapshot tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        foreach (['ranking_snapshots', 'ranking_snapshot_entries'] as $table) {
            DB::statement("ALTER TABLE results.{$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE results.{$table} FORCE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY {$table}_school_isolation ON results.{$table}
                USING (
                    NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                    AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
                WITH CHECK (
                    NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                    AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            ");
        }
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        foreach (['ranking_snapshot_entries', 'ranking_snapshots'] as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_school_isolation ON results.{$table}");
            DB::statement("ALTER TABLE results.{$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE results.{$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
