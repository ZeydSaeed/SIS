<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase FIN-U08 — FORCE RLS + reject hard DELETE on finance.transactions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE finance.transactions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE finance.transactions FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS transactions_school_isolation ON finance.transactions');
        DB::statement("
            CREATE POLICY transactions_school_isolation ON finance.transactions
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
            CREATE OR REPLACE FUNCTION finance.reject_transactions_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of finance.transactions is forbidden';
            END;
            $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS transactions_reject_delete ON finance.transactions');
        DB::statement("
            CREATE TRIGGER transactions_reject_delete
            BEFORE DELETE ON finance.transactions
            FOR EACH ROW
            EXECUTE FUNCTION finance.reject_transactions_delete()
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS transactions_reject_delete ON finance.transactions');
        DB::statement('DROP FUNCTION IF EXISTS finance.reject_transactions_delete()');
        DB::statement('DROP POLICY IF EXISTS transactions_school_isolation ON finance.transactions');
        DB::statement('ALTER TABLE finance.transactions NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE finance.transactions DISABLE ROW LEVEL SECURITY');
    }
};
