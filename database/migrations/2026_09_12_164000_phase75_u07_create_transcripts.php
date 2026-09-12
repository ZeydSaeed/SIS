<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 7.5-U07 — physicalize results.transcripts as issued immutable metadata (no PDF engine).
 * Supersedes blueprint sketch; object count unchanged (existing blueprint object).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS enrollments_id_school_id_unique
            ON enrollment.enrollments (id, school_id)
        ');
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS enrollments_id_academic_year_id_unique
            ON enrollment.enrollments (id, academic_year_id)
        ');

        DB::statement("
            CREATE TABLE results.transcripts (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                student_id BIGINT NOT NULL,
                enrollment_id BIGINT NOT NULL,
                academic_year_id BIGINT NOT NULL,
                transcript_version INT NOT NULL,
                transcript_number VARCHAR(50) NOT NULL,
                lifecycle_status SMALLINT NOT NULL,
                is_current BOOLEAN NOT NULL DEFAULT false,
                storage_key VARCHAR(500),
                payload_hash VARCHAR(64) NOT NULL,
                source_fingerprint VARCHAR(128) NOT NULL,
                policy_pin JSONB NOT NULL DEFAULT '{}'::jsonb,
                issued_at TIMESTAMPTZ NOT NULL,
                issued_by BIGINT,
                superseded_at TIMESTAMPTZ,
                correlation_id VARCHAR(64),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT transcripts_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT transcripts_student_fk
                    FOREIGN KEY (student_id) REFERENCES students.students(id) ON DELETE RESTRICT,
                CONSTRAINT transcripts_year_fk
                    FOREIGN KEY (academic_year_id) REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                CONSTRAINT transcripts_enrollment_school_fk
                    FOREIGN KEY (enrollment_id, school_id)
                    REFERENCES enrollment.enrollments(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT transcripts_enrollment_year_fk
                    FOREIGN KEY (enrollment_id, academic_year_id)
                    REFERENCES enrollment.enrollments(id, academic_year_id) ON DELETE RESTRICT,
                CONSTRAINT transcripts_issued_by_fk
                    FOREIGN KEY (issued_by) REFERENCES public.users(id) ON DELETE SET NULL
            )
        ");

        DB::statement('
            ALTER TABLE results.transcripts
            ADD CONSTRAINT transcripts_lifecycle_check
            CHECK (lifecycle_status BETWEEN 1 AND 3)
        ');
        DB::statement('
            ALTER TABLE results.transcripts
            ADD CONSTRAINT transcripts_version_check
            CHECK (transcript_version >= 1)
        ');
        DB::statement('
            ALTER TABLE results.transcripts
            ADD CONSTRAINT transcripts_superseded_not_current_check
            CHECK (lifecycle_status <> 3 OR is_current = false)
        ');

        DB::statement('
            CREATE UNIQUE INDEX transcripts_number_uidx
            ON results.transcripts (transcript_number)
        ');
        DB::statement('
            CREATE UNIQUE INDEX transcripts_identity_version_uidx
            ON results.transcripts (school_id, enrollment_id, academic_year_id, transcript_version)
        ');
        DB::statement('
            CREATE UNIQUE INDEX transcripts_current_uidx
            ON results.transcripts (school_id, enrollment_id, academic_year_id)
            WHERE is_current
        ');
        DB::statement('
            CREATE INDEX transcripts_student_year_idx
            ON results.transcripts (student_id, academic_year_id)
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION results.reject_transcripts_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of results.transcripts is forbidden';
            END; $$
        ");
        DB::statement('
            CREATE TRIGGER transcripts_reject_delete
            BEFORE DELETE ON results.transcripts
            FOR EACH ROW EXECUTE FUNCTION results.reject_transcripts_delete()
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS transcripts_reject_delete ON results.transcripts');
        DB::statement('DROP FUNCTION IF EXISTS results.reject_transcripts_delete()');
        DB::statement('DROP TABLE IF EXISTS results.transcripts');
    }
};
