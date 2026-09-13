<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase STU-DOC-U01 — physicalize students.student_documents (metadata + FORCE RLS).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement("
            CREATE TABLE IF NOT EXISTS students.student_documents (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                student_id BIGINT NOT NULL,
                document_type SMALLINT NOT NULL,
                storage_key VARCHAR(500) NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                file_size BIGINT NOT NULL,
                file_hash VARCHAR(64) NOT NULL,
                uploaded_by BIGINT,
                status SMALLINT NOT NULL DEFAULT 1,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT students_student_documents_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT students_student_documents_student_fk
                    FOREIGN KEY (student_id) REFERENCES students.students(id) ON DELETE RESTRICT,
                CONSTRAINT students_student_documents_uploaded_by_fk
                    FOREIGN KEY (uploaded_by) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT students_student_documents_size_check
                    CHECK (file_size >= 0),
                CONSTRAINT students_student_documents_type_check
                    CHECK (document_type IN (1, 2, 3, 4, 9)),
                CONSTRAINT students_student_documents_status_check
                    CHECK (status IN (1, 2)),
                CONSTRAINT students_student_documents_hash_check
                    CHECK (file_hash ~ '^[a-f0-9]{64}$')
            )
        ");

        DB::statement('
            CREATE INDEX IF NOT EXISTS students_student_documents_student_idx
            ON students.student_documents (student_id)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS students_student_documents_student_type_idx
            ON students.student_documents (student_id, document_type)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS students_student_documents_school_idx
            ON students.student_documents (school_id, created_at)
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION students.reject_student_documents_hard_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard DELETE of students.student_documents is forbidden — void via status'
                    USING ERRCODE = 'check_violation';
            END; $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS students_student_documents_reject_hard_delete ON students.student_documents');
        DB::statement("
            CREATE TRIGGER students_student_documents_reject_hard_delete
            BEFORE DELETE ON students.student_documents
            FOR EACH ROW
            EXECUTE FUNCTION students.reject_student_documents_hard_delete()
        ");

        DB::statement('ALTER TABLE students.student_documents ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE students.student_documents FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS student_documents_school_isolation ON students.student_documents');
        DB::statement("
            CREATE POLICY student_documents_school_isolation ON students.student_documents
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

        DB::statement('DROP POLICY IF EXISTS student_documents_school_isolation ON students.student_documents');
        DB::statement('ALTER TABLE students.student_documents NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE students.student_documents DISABLE ROW LEVEL SECURITY');
        DB::statement('DROP TRIGGER IF EXISTS students_student_documents_reject_hard_delete ON students.student_documents');
        DB::statement('DROP FUNCTION IF EXISTS students.reject_student_documents_hard_delete()');
        DB::statement('DROP TABLE IF EXISTS students.student_documents');
    }
};
