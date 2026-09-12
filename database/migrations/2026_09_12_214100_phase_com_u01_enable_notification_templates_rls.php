<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase COM-U01 — FORCE RLS + reject hard DELETE on notification_templates.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE communication.notification_templates ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE communication.notification_templates FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS notification_templates_school_isolation ON communication.notification_templates');
        DB::statement("
            CREATE POLICY notification_templates_school_isolation ON communication.notification_templates
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
            CREATE OR REPLACE FUNCTION communication.reject_notification_templates_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of communication.notification_templates is forbidden';
            END;
            $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS notification_templates_reject_delete ON communication.notification_templates');
        DB::statement("
            CREATE TRIGGER notification_templates_reject_delete
            BEFORE DELETE ON communication.notification_templates
            FOR EACH ROW
            EXECUTE FUNCTION communication.reject_notification_templates_delete()
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS notification_templates_reject_delete ON communication.notification_templates');
        DB::statement('DROP FUNCTION IF EXISTS communication.reject_notification_templates_delete()');
        DB::statement('DROP POLICY IF EXISTS notification_templates_school_isolation ON communication.notification_templates');
        DB::statement('ALTER TABLE communication.notification_templates NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE communication.notification_templates DISABLE ROW LEVEL SECURITY');
    }
};
