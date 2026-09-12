<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase WF-U04 — FORCE RLS + reject hard DELETE on approval_requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE workflow.approval_requests ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE workflow.approval_requests FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS approval_requests_school_isolation ON workflow.approval_requests');
        DB::statement("
            CREATE POLICY approval_requests_school_isolation ON workflow.approval_requests
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
            CREATE OR REPLACE FUNCTION workflow.reject_approval_requests_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of workflow.approval_requests is forbidden';
            END;
            $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS approval_requests_reject_delete ON workflow.approval_requests');
        DB::statement("
            CREATE TRIGGER approval_requests_reject_delete
            BEFORE DELETE ON workflow.approval_requests
            FOR EACH ROW
            EXECUTE FUNCTION workflow.reject_approval_requests_delete()
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS approval_requests_reject_delete ON workflow.approval_requests');
        DB::statement('DROP FUNCTION IF EXISTS workflow.reject_approval_requests_delete()');
        DB::statement('DROP POLICY IF EXISTS approval_requests_school_isolation ON workflow.approval_requests');
        DB::statement('ALTER TABLE workflow.approval_requests NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE workflow.approval_requests DISABLE ROW LEVEL SECURITY');
    }
};
