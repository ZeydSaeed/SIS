<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase DOC-U01 — FORCE RLS + reject hard DELETE on documents.files.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE documents.files ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE documents.files FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS files_school_isolation ON documents.files');
        DB::statement("
            CREATE POLICY files_school_isolation ON documents.files
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
            CREATE OR REPLACE FUNCTION documents.reject_files_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of documents.files is forbidden';
            END;
            $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS files_reject_delete ON documents.files');
        DB::statement("
            CREATE TRIGGER files_reject_delete
            BEFORE DELETE ON documents.files
            FOR EACH ROW
            EXECUTE FUNCTION documents.reject_files_delete()
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS files_reject_delete ON documents.files');
        DB::statement('DROP FUNCTION IF EXISTS documents.reject_files_delete()');
        DB::statement('DROP POLICY IF EXISTS files_school_isolation ON documents.files');
        DB::statement('ALTER TABLE documents.files NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE documents.files DISABLE ROW LEVEL SECURITY');
    }
};
