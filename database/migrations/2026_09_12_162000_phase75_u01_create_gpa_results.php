<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 7.5-U01 — results.gpa_results versioned derived GPA (NOT grade SSOT).
 * v1: year-scope PERCENT_100 from official annual average.
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
            CREATE TABLE results.gpa_results (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                enrollment_id BIGINT NOT NULL,
                student_id BIGINT NOT NULL,
                academic_year_id BIGINT NOT NULL,
                gpa_scope SMALLINT NOT NULL DEFAULT 1,
                result_version INT NOT NULL,
                lifecycle_status SMALLINT NOT NULL,
                is_official BOOLEAN NOT NULL DEFAULT false,
                is_current_operational BOOLEAN NOT NULL DEFAULT false,
                is_current_official BOOLEAN NOT NULL DEFAULT false,
                gpa_value NUMERIC(8,2),
                scale_code VARCHAR(32) NOT NULL DEFAULT 'PERCENT_100',
                source_annual_result_id BIGINT,
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
                CONSTRAINT gpa_results_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT gpa_results_student_fk
                    FOREIGN KEY (student_id) REFERENCES students.students(id) ON DELETE RESTRICT,
                CONSTRAINT gpa_results_academic_year_fk
                    FOREIGN KEY (academic_year_id) REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                CONSTRAINT gpa_results_enrollment_school_fk
                    FOREIGN KEY (enrollment_id, school_id)
                    REFERENCES enrollment.enrollments(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT gpa_results_enrollment_year_fk
                    FOREIGN KEY (enrollment_id, academic_year_id)
                    REFERENCES enrollment.enrollments(id, academic_year_id) ON DELETE RESTRICT,
                CONSTRAINT gpa_results_source_annual_fk
                    FOREIGN KEY (source_annual_result_id)
                    REFERENCES results.annual_results(id) ON DELETE RESTRICT,
                CONSTRAINT gpa_results_created_by_fk
                    FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL
            )
        ");

        DB::statement('
            ALTER TABLE results.gpa_results
            ADD CONSTRAINT gpa_results_lifecycle_status_check
            CHECK (lifecycle_status BETWEEN 1 AND 3)
        ');
        DB::statement('
            ALTER TABLE results.gpa_results
            ADD CONSTRAINT gpa_results_result_version_check
            CHECK (result_version >= 1)
        ');
        DB::statement('
            ALTER TABLE results.gpa_results
            ADD CONSTRAINT gpa_results_scope_check
            CHECK (gpa_scope = 1)
        ');
        DB::statement("
            ALTER TABLE results.gpa_results
            ADD CONSTRAINT gpa_results_scale_code_check
            CHECK (scale_code = 'PERCENT_100')
        ");
        DB::statement('
            ALTER TABLE results.gpa_results
            ADD CONSTRAINT gpa_results_official_flags_check
            CHECK (
                (is_official = false AND is_current_official = false)
                OR (is_official = true)
            )
        ');
        DB::statement('
            ALTER TABLE results.gpa_results
            ADD CONSTRAINT gpa_results_superseded_not_current_check
            CHECK (
                lifecycle_status <> 3
                OR (is_current_operational = false AND is_current_official = false)
            )
        ');
        DB::statement('
            ALTER TABLE results.gpa_results
            ADD CONSTRAINT gpa_results_finalized_requires_official_check
            CHECK (
                lifecycle_status <> 2
                OR (is_official = true AND finalized_at IS NOT NULL)
            )
        ');

        DB::statement('
            CREATE UNIQUE INDEX gpa_results_identity_version_uidx
            ON results.gpa_results (school_id, enrollment_id, academic_year_id, gpa_scope, result_version)
        ');
        DB::statement('
            CREATE UNIQUE INDEX gpa_results_current_operational_uidx
            ON results.gpa_results (school_id, enrollment_id, academic_year_id, gpa_scope)
            WHERE is_current_operational
        ');
        DB::statement('
            CREATE UNIQUE INDEX gpa_results_current_official_uidx
            ON results.gpa_results (school_id, enrollment_id, academic_year_id, gpa_scope)
            WHERE is_current_official
        ');
        DB::statement('
            CREATE INDEX gpa_results_student_year_idx
            ON results.gpa_results (student_id, academic_year_id)
        ');
        DB::statement('
            CREATE INDEX gpa_results_school_year_idx
            ON results.gpa_results (school_id, academic_year_id)
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION results.reject_gpa_results_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of results.gpa_results is forbidden';
            END;
            $$
        ");
        DB::statement('
            CREATE TRIGGER gpa_results_reject_delete
            BEFORE DELETE ON results.gpa_results
            FOR EACH ROW
            EXECUTE FUNCTION results.reject_gpa_results_delete()
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS gpa_results_reject_delete ON results.gpa_results');
        DB::statement('DROP FUNCTION IF EXISTS results.reject_gpa_results_delete()');
        DB::statement('DROP TABLE IF EXISTS results.gpa_results');
    }
};
