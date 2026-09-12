<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 7.4-U01 — results.term_results as versioned derived snapshots (NOT grade SSOT).
 *
 * Supersedes blueprint sketch columns (no letter/GPA/rank in v1 — Design Lock HD-7.4-010).
 * No partition in v1 (3C.2). No hard-delete (reject trigger).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        $this->prepareCompositeUniqueSupports();
        $this->createTermResults();
        $this->addConstraintsAndIndexes();
        $this->addRejectDeleteTrigger();
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS term_results_reject_delete ON results.term_results');
        DB::statement('DROP FUNCTION IF EXISTS results.reject_term_results_delete()');
        DB::statement('DROP TABLE IF EXISTS results.term_results');
    }

    private function prepareCompositeUniqueSupports(): void
    {
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS enrollments_id_school_id_unique
            ON enrollment.enrollments (id, school_id)
        ');
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS enrollments_id_academic_year_id_unique
            ON enrollment.enrollments (id, academic_year_id)
        ');
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS terms_id_academic_year_id_unique
            ON academic.terms (id, academic_year_id)
        ');
    }

    private function createTermResults(): void
    {
        DB::statement("
            CREATE TABLE results.term_results (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                enrollment_id BIGINT NOT NULL,
                student_id BIGINT NOT NULL,
                academic_year_id BIGINT NOT NULL,
                term_id BIGINT NOT NULL,
                subject_id BIGINT NOT NULL,
                result_version INT NOT NULL,
                lifecycle_status SMALLINT NOT NULL,
                is_official BOOLEAN NOT NULL DEFAULT false,
                is_current_operational BOOLEAN NOT NULL DEFAULT false,
                is_current_official BOOLEAN NOT NULL DEFAULT false,
                weighted_total NUMERIC(8,2),
                pass_fail SMALLINT,
                incomplete BOOLEAN NOT NULL DEFAULT false,
                source_fingerprint VARCHAR(128) NOT NULL,
                calculation_version INT NOT NULL DEFAULT 1,
                policy_pin JSONB NOT NULL DEFAULT '{}'::jsonb,
                calculated_at TIMESTAMPTZ NOT NULL,
                finalized_at TIMESTAMPTZ,
                superseded_at TIMESTAMPTZ,
                correlation_id VARCHAR(64),
                created_by BIGINT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT term_results_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT term_results_student_fk
                    FOREIGN KEY (student_id) REFERENCES students.students(id) ON DELETE RESTRICT,
                CONSTRAINT term_results_subject_fk
                    FOREIGN KEY (subject_id) REFERENCES curriculum.subjects(id) ON DELETE RESTRICT,
                CONSTRAINT term_results_academic_year_fk
                    FOREIGN KEY (academic_year_id) REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                CONSTRAINT term_results_enrollment_school_fk
                    FOREIGN KEY (enrollment_id, school_id)
                    REFERENCES enrollment.enrollments(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT term_results_enrollment_year_fk
                    FOREIGN KEY (enrollment_id, academic_year_id)
                    REFERENCES enrollment.enrollments(id, academic_year_id) ON DELETE RESTRICT,
                CONSTRAINT term_results_term_year_fk
                    FOREIGN KEY (term_id, academic_year_id)
                    REFERENCES academic.terms(id, academic_year_id) ON DELETE RESTRICT,
                CONSTRAINT term_results_created_by_fk
                    FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL
            )
        ");
    }

    private function addConstraintsAndIndexes(): void
    {
        DB::statement('
            ALTER TABLE results.term_results
            ADD CONSTRAINT term_results_lifecycle_status_check
            CHECK (lifecycle_status BETWEEN 1 AND 3)
        ');
        DB::statement('
            ALTER TABLE results.term_results
            ADD CONSTRAINT term_results_result_version_check
            CHECK (result_version >= 1)
        ');
        DB::statement('
            ALTER TABLE results.term_results
            ADD CONSTRAINT term_results_pass_fail_check
            CHECK (pass_fail IS NULL OR pass_fail IN (0, 1))
        ');
        DB::statement('
            ALTER TABLE results.term_results
            ADD CONSTRAINT term_results_official_flags_check
            CHECK (
                (is_official = false AND is_current_official = false)
                OR (is_official = true)
            )
        ');
        DB::statement('
            ALTER TABLE results.term_results
            ADD CONSTRAINT term_results_superseded_not_current_check
            CHECK (
                lifecycle_status <> 3
                OR (is_current_operational = false AND is_current_official = false)
            )
        ');
        DB::statement('
            ALTER TABLE results.term_results
            ADD CONSTRAINT term_results_finalized_requires_official_check
            CHECK (
                lifecycle_status <> 2
                OR (is_official = true AND finalized_at IS NOT NULL)
            )
        ');

        DB::statement('
            CREATE UNIQUE INDEX term_results_identity_version_uidx
            ON results.term_results (
                school_id, enrollment_id, term_id, subject_id, result_version
            )
        ');
        DB::statement('
            CREATE UNIQUE INDEX term_results_current_operational_uidx
            ON results.term_results (school_id, enrollment_id, term_id, subject_id)
            WHERE is_current_operational
        ');
        DB::statement('
            CREATE UNIQUE INDEX term_results_current_official_uidx
            ON results.term_results (school_id, enrollment_id, term_id, subject_id)
            WHERE is_current_official
        ');
        DB::statement('
            CREATE INDEX term_results_student_year_term_idx
            ON results.term_results (student_id, academic_year_id, term_id)
        ');
        DB::statement('
            CREATE INDEX term_results_school_year_subject_idx
            ON results.term_results (school_id, academic_year_id, subject_id)
        ');
        DB::statement('
            CREATE INDEX term_results_enrollment_year_idx
            ON results.term_results (enrollment_id, academic_year_id)
        ');
    }

    private function addRejectDeleteTrigger(): void
    {
        DB::statement("
            CREATE OR REPLACE FUNCTION results.reject_term_results_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of results.term_results is forbidden';
            END;
            $$
        ");
        DB::statement('
            CREATE TRIGGER term_results_reject_delete
            BEFORE DELETE ON results.term_results
            FOR EACH ROW
            EXECUTE FUNCTION results.reject_term_results_delete()
        ');
    }
};
