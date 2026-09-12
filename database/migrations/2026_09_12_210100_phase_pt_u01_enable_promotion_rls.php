<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase PT-U01 — FORCE RLS + reject hard DELETE on promotion.* .
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        foreach (['rules', 'records'] as $table) {
            DB::statement("ALTER TABLE promotion.{$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE promotion.{$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS {$table}_school_isolation ON promotion.{$table}");
            DB::statement("
                CREATE POLICY {$table}_school_isolation ON promotion.{$table}
                USING (
                    NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                    AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
                WITH CHECK (
                    NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                    AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            ");

            DB::statement("
                CREATE OR REPLACE FUNCTION promotion.reject_{$table}_delete()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $$
                BEGIN
                    RAISE EXCEPTION 'Hard delete of promotion.{$table} is forbidden';
                END;
                $$
            ");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_reject_delete ON promotion.{$table}");
            DB::statement("
                CREATE TRIGGER {$table}_reject_delete
                BEFORE DELETE ON promotion.{$table}
                FOR EACH ROW
                EXECUTE FUNCTION promotion.reject_{$table}_delete()
            ");
        }
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        foreach (['rules', 'records'] as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_reject_delete ON promotion.{$table}");
            DB::statement("DROP FUNCTION IF EXISTS promotion.reject_{$table}_delete()");
            DB::statement("DROP POLICY IF EXISTS {$table}_school_isolation ON promotion.{$table}");
            DB::statement("ALTER TABLE promotion.{$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE promotion.{$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
