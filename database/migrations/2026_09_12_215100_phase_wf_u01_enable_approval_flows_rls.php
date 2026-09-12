<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase WF-U01 — FORCE RLS + reject hard DELETE on approval_flows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE workflow.approval_flows ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE workflow.approval_flows FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS approval_flows_school_isolation ON workflow.approval_flows');
        DB::statement("
            CREATE POLICY approval_flows_school_isolation ON workflow.approval_flows
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
            CREATE OR REPLACE FUNCTION workflow.reject_approval_flows_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of workflow.approval_flows is forbidden';
            END;
            $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS approval_flows_reject_delete ON workflow.approval_flows');
        DB::statement("
            CREATE TRIGGER approval_flows_reject_delete
            BEFORE DELETE ON workflow.approval_flows
            FOR EACH ROW
            EXECUTE FUNCTION workflow.reject_approval_flows_delete()
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS approval_flows_reject_delete ON workflow.approval_flows');
        DB::statement('DROP FUNCTION IF EXISTS workflow.reject_approval_flows_delete()');
        DB::statement('DROP POLICY IF EXISTS approval_flows_school_isolation ON workflow.approval_flows');
        DB::statement('ALTER TABLE workflow.approval_flows NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE workflow.approval_flows DISABLE ROW LEVEL SECURITY');
    }
};
