<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase FIN-U01 — FORCE RLS + reject hard DELETE on finance.fee_types.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE finance.fee_types ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE finance.fee_types FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS fee_types_school_isolation ON finance.fee_types');
        DB::statement("
            CREATE POLICY fee_types_school_isolation ON finance.fee_types
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
            CREATE OR REPLACE FUNCTION finance.reject_fee_types_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of finance.fee_types is forbidden';
            END;
            $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS fee_types_reject_delete ON finance.fee_types');
        DB::statement("
            CREATE TRIGGER fee_types_reject_delete
            BEFORE DELETE ON finance.fee_types
            FOR EACH ROW
            EXECUTE FUNCTION finance.reject_fee_types_delete()
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS fee_types_reject_delete ON finance.fee_types');
        DB::statement('DROP FUNCTION IF EXISTS finance.reject_fee_types_delete()');
        DB::statement('DROP POLICY IF EXISTS fee_types_school_isolation ON finance.fee_types');
        DB::statement('ALTER TABLE finance.fee_types NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE finance.fee_types DISABLE ROW LEVEL SECURITY');
    }
};
