<?php

use App\Database\GraduationTenantProtection;
use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3C.12 M06–M07 — Completion outcomes + versions (deferred pointer FK; Option A RLS).
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

        DB::statement('
            CREATE TABLE graduation.completion_outcomes (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                enrollment_id BIGINT NOT NULL,
                student_id BIGINT NOT NULL,
                academic_year_id BIGINT NOT NULL,
                specialization_id BIGINT,
                current_official_version_id BIGINT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                created_by BIGINT,
                CONSTRAINT completion_outcomes_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT completion_outcomes_student_fk
                    FOREIGN KEY (student_id) REFERENCES students.students(id) ON DELETE RESTRICT,
                CONSTRAINT completion_outcomes_enrollment_school_fk
                    FOREIGN KEY (enrollment_id, school_id)
                    REFERENCES enrollment.enrollments(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT completion_outcomes_enrollment_year_fk
                    FOREIGN KEY (enrollment_id, academic_year_id)
                    REFERENCES enrollment.enrollments(id, academic_year_id) ON DELETE RESTRICT,
                CONSTRAINT completion_outcomes_school_enrollment_uq UNIQUE (school_id, enrollment_id)
            )
        ');
        GraduationTenantProtection::protect('completion_outcomes');

        DB::statement('
            CREATE TABLE graduation.completion_outcome_versions (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                completion_outcome_id BIGINT NOT NULL,
                version_no INTEGER NOT NULL,
                lifecycle_status SMALLINT NOT NULL DEFAULT 1,
                evaluation_status SMALLINT NOT NULL DEFAULT 1,
                eligibility_status SMALLINT NOT NULL DEFAULT 1,
                eligibility_policy_version_id BIGINT NOT NULL,
                calculation_version VARCHAR(64) NOT NULL,
                source_fingerprint VARCHAR(128),
                policy_fingerprint VARCHAR(128),
                evaluated_at TIMESTAMPTZ,
                eligibility_determined_at TIMESTAMPTZ,
                is_current_official BOOLEAN NOT NULL DEFAULT false,
                supersedes_version_id BIGINT,
                superseded_by_version_id BIGINT,
                correlation_id VARCHAR(64),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                created_by BIGINT,
                CONSTRAINT completion_outcome_versions_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT completion_outcome_versions_outcome_fk
                    FOREIGN KEY (completion_outcome_id) REFERENCES graduation.completion_outcomes(id) ON DELETE RESTRICT,
                CONSTRAINT completion_outcome_versions_policy_ver_fk
                    FOREIGN KEY (eligibility_policy_version_id, school_id)
                    REFERENCES graduation.eligibility_policy_versions(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT completion_outcome_versions_version_chk CHECK (version_no >= 1),
                CONSTRAINT completion_outcome_versions_lifecycle_chk CHECK (lifecycle_status BETWEEN 1 AND 3),
                CONSTRAINT completion_outcome_versions_eval_chk CHECK (evaluation_status BETWEEN 1 AND 5),
                CONSTRAINT completion_outcome_versions_elig_chk CHECK (eligibility_status BETWEEN 1 AND 5),
                CONSTRAINT completion_outcome_versions_uq UNIQUE (completion_outcome_id, version_no)
            )
        ');
        DB::statement('
            CREATE UNIQUE INDEX completion_outcome_versions_current_official_uidx
            ON graduation.completion_outcome_versions (completion_outcome_id)
            WHERE is_current_official
        ');
        DB::statement('
            CREATE UNIQUE INDEX completion_outcome_versions_id_school_uidx
            ON graduation.completion_outcome_versions (id, school_id)
        ');
        DB::statement('
            ALTER TABLE graduation.completion_outcome_versions
            ADD CONSTRAINT completion_outcome_versions_supersedes_fk
                FOREIGN KEY (supersedes_version_id) REFERENCES graduation.completion_outcome_versions(id) ON DELETE RESTRICT
        ');
        DB::statement('
            ALTER TABLE graduation.completion_outcome_versions
            ADD CONSTRAINT completion_outcome_versions_superseded_by_fk
                FOREIGN KEY (superseded_by_version_id) REFERENCES graduation.completion_outcome_versions(id) ON DELETE RESTRICT
        ');
        GraduationTenantProtection::protect('completion_outcome_versions');

        DB::statement('
            ALTER TABLE graduation.completion_outcomes
            ADD CONSTRAINT completion_outcomes_current_official_fk
                FOREIGN KEY (current_official_version_id)
                REFERENCES graduation.completion_outcome_versions(id) ON DELETE RESTRICT
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE graduation.completion_outcomes DROP CONSTRAINT IF EXISTS completion_outcomes_current_official_fk');
        GraduationTenantProtection::unprotect('completion_outcome_versions');
        GraduationTenantProtection::unprotect('completion_outcomes');
        DB::statement('DROP TABLE IF EXISTS graduation.completion_outcome_versions');
        DB::statement('DROP TABLE IF EXISTS graduation.completion_outcomes');
    }
};
