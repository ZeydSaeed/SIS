<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 7.4-U01 — fail-closed RLS + FORCE on results.term_results.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE results.term_results ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE results.term_results FORCE ROW LEVEL SECURITY');

        DB::statement("
            CREATE POLICY term_results_school_isolation ON results.term_results
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

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS term_results_school_isolation ON results.term_results');
        DB::statement('ALTER TABLE results.term_results NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE results.term_results DISABLE ROW LEVEL SECURITY');
    }
};
