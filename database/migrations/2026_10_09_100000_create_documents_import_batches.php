<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «استيراد Excel» — an uploaded spreadsheet is a document: the file stays in object storage (storage_key), the batch
 * row tracks its lifecycle and each parsed row is kept for the preview, the commit and the error report.
 *
 * documents.import_batches  kind (teachers · structure · students · subjects), status 1 parsing · 2 previewed ·
 *                           3 committing · 4 committed · 5 failed · 6 cancelled; counts and the result report.
 * documents.import_rows     row number, normalised data, planned action (1 create · 2 update · 3 skip),
 *                           status (1 valid · 2 error · 3 duplicate · 4 committed · 5 failed), errors, entity id.
 *
 * school_id NOT NULL + FORCE RLS on app.current_school_id; hard DELETE rejected (a batch ends by status).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement("
            CREATE TABLE documents.import_batches (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                academic_year_id BIGINT REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                kind VARCHAR(20) NOT NULL CHECK (kind IN ('teachers', 'structure', 'students', 'subjects')),
                status SMALLINT NOT NULL DEFAULT 1 CHECK (status BETWEEN 1 AND 6),
                file_name VARCHAR(255) NOT NULL,
                storage_key VARCHAR(500) NOT NULL,
                total_rows INTEGER NOT NULL DEFAULT 0,
                valid_rows INTEGER NOT NULL DEFAULT 0,
                error_rows INTEGER NOT NULL DEFAULT 0,
                duplicate_rows INTEGER NOT NULL DEFAULT 0,
                result JSONB,
                error TEXT,
                created_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                committed_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                committed_at TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
        DB::statement('CREATE INDEX import_batches_school_idx ON documents.import_batches (school_id, created_at DESC)');

        DB::statement('
            CREATE TABLE documents.import_rows (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                batch_id BIGINT NOT NULL REFERENCES documents.import_batches(id) ON DELETE RESTRICT,
                row_number INTEGER NOT NULL CHECK (row_number >= 1),
                data JSONB NOT NULL,
                action SMALLINT NOT NULL CHECK (action IN (1, 2, 3)),
                status SMALLINT NOT NULL CHECK (status BETWEEN 1 AND 5),
                errors JSONB,
                entity_id BIGINT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT import_rows_batch_row_unique UNIQUE (batch_id, row_number)
            )
        ');
        DB::statement('CREATE INDEX import_rows_batch_status_idx ON documents.import_rows (batch_id, status)');

        DB::statement("
            CREATE OR REPLACE FUNCTION documents.reject_import_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of documents.% is forbidden — end the batch by status', TG_TABLE_NAME
                    USING ERRCODE = 'check_violation';
            END; $$
        ");
        foreach (['import_batches', 'import_rows'] as $table) {
            DB::statement("ALTER TABLE documents.{$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE documents.{$table} FORCE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY {$table}_school_isolation ON documents.{$table}
                USING (
                    NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                    AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
                WITH CHECK (
                    NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                    AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            ");
            DB::statement("CREATE TRIGGER {$table}_reject_delete BEFORE DELETE ON documents.{$table} FOR EACH ROW EXECUTE FUNCTION documents.reject_import_delete()");
        }
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS documents.import_rows');
        DB::statement('DROP TABLE IF EXISTS documents.import_batches');
        DB::statement('DROP FUNCTION IF EXISTS documents.reject_import_delete()');
    }
};
