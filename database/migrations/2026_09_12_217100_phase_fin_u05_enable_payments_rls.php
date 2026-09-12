<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase FIN-U05 — FORCE RLS + reject hard DELETE on finance.payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE finance.payments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE finance.payments FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS payments_school_isolation ON finance.payments');
        DB::statement("
            CREATE POLICY payments_school_isolation ON finance.payments
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
            CREATE OR REPLACE FUNCTION finance.reject_payments_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of finance.payments is forbidden';
            END;
            $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS payments_reject_delete ON finance.payments');
        DB::statement("
            CREATE TRIGGER payments_reject_delete
            BEFORE DELETE ON finance.payments
            FOR EACH ROW
            EXECUTE FUNCTION finance.reject_payments_delete()
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS payments_reject_delete ON finance.payments');
        DB::statement('DROP FUNCTION IF EXISTS finance.reject_payments_delete()');
        DB::statement('DROP POLICY IF EXISTS payments_school_isolation ON finance.payments');
        DB::statement('ALTER TABLE finance.payments NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE finance.payments DISABLE ROW LEVEL SECURITY');
    }
};
