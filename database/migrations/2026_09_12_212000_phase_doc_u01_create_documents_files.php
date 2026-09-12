<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase DOC-U01 — Physicalize documents.files (metadata only).
 * HD: ADD school_id for FORCE RLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS documents');

        DB::statement("
            CREATE TABLE documents.files (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id BIGINT NOT NULL,
                document_type SMALLINT NOT NULL,
                storage_key VARCHAR(500) NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                file_size BIGINT NOT NULL,
                file_hash VARCHAR(64) NOT NULL,
                uploaded_by BIGINT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT documents_files_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT documents_files_uploaded_by_fk
                    FOREIGN KEY (uploaded_by) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT documents_files_size_check
                    CHECK (file_size >= 0),
                CONSTRAINT documents_files_document_type_check
                    CHECK (document_type BETWEEN 1 AND 99)
            )
        ");

        DB::statement('
            CREATE INDEX documents_files_entity_idx
            ON documents.files (entity_type, entity_id)
        ');
        DB::statement('
            CREATE INDEX documents_files_hash_idx
            ON documents.files (file_hash)
        ');
        DB::statement('
            CREATE INDEX documents_files_school_idx
            ON documents.files (school_id, created_at)
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS documents.files');
    }
};
