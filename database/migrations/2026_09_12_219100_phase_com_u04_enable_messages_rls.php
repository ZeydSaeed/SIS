<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase COM-U04 — FORCE RLS + reject hard DELETE on communication.messages.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE communication.messages ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE communication.messages FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS messages_school_isolation ON communication.messages');
        DB::statement("
            CREATE POLICY messages_school_isolation ON communication.messages
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
            CREATE OR REPLACE FUNCTION communication.reject_messages_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of communication.messages is forbidden';
            END;
            $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS messages_reject_delete ON communication.messages');
        DB::statement("
            CREATE TRIGGER messages_reject_delete
            BEFORE DELETE ON communication.messages
            FOR EACH ROW
            EXECUTE FUNCTION communication.reject_messages_delete()
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS messages_reject_delete ON communication.messages');
        DB::statement('DROP FUNCTION IF EXISTS communication.reject_messages_delete()');
        DB::statement('DROP POLICY IF EXISTS messages_school_isolation ON communication.messages');
        DB::statement('ALTER TABLE communication.messages NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE communication.messages DISABLE ROW LEVEL SECURITY');
    }
};
