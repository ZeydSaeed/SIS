<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase FIN-U03 — FORCE RLS + reject hard DELETE on finance.student_fees.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE finance.student_fees ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE finance.student_fees FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS student_fees_school_isolation ON finance.student_fees');
        DB::statement("
            CREATE POLICY student_fees_school_isolation ON finance.student_fees
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
            CREATE OR REPLACE FUNCTION finance.reject_student_fees_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of finance.student_fees is forbidden';
            END;
            $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS student_fees_reject_delete ON finance.student_fees');
        DB::statement("
            CREATE TRIGGER student_fees_reject_delete
            BEFORE DELETE ON finance.student_fees
            FOR EACH ROW
            EXECUTE FUNCTION finance.reject_student_fees_delete()
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS student_fees_reject_delete ON finance.student_fees');
        DB::statement('DROP FUNCTION IF EXISTS finance.reject_student_fees_delete()');
        DB::statement('DROP POLICY IF EXISTS student_fees_school_isolation ON finance.student_fees');
        DB::statement('ALTER TABLE finance.student_fees NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE finance.student_fees DISABLE ROW LEVEL SECURITY');
    }
};
