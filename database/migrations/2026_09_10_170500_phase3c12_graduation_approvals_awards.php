<?php

use App\Database\GraduationTenantProtection;
use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3C.12 M11–M13 — Approvals + awards + award versions (Option A RLS).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            CREATE TABLE graduation.graduation_approvals (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                enrollment_id BIGINT NOT NULL,
                completion_outcome_version_id BIGINT NOT NULL,
                attempt_no INTEGER NOT NULL,
                decision_status SMALLINT NOT NULL DEFAULT 1,
                requested_at TIMESTAMPTZ NOT NULL,
                requested_by BIGINT,
                decided_at TIMESTAMPTZ,
                decided_by BIGINT,
                decision_reason_ref VARCHAR(128),
                correlation_id VARCHAR(64),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT graduation_approvals_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT graduation_approvals_enrollment_school_fk
                    FOREIGN KEY (enrollment_id, school_id)
                    REFERENCES enrollment.enrollments(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT graduation_approvals_cov_fk
                    FOREIGN KEY (completion_outcome_version_id, school_id)
                    REFERENCES graduation.completion_outcome_versions(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT graduation_approvals_attempt_chk CHECK (attempt_no >= 1),
                CONSTRAINT graduation_approvals_decision_chk CHECK (decision_status BETWEEN 1 AND 3),
                CONSTRAINT graduation_approvals_decided_fields_chk CHECK (
                    decision_status = 1
                    OR (decided_at IS NOT NULL)
                ),
                CONSTRAINT graduation_approvals_attempt_uq UNIQUE (completion_outcome_version_id, attempt_no)
            )
        ');
        GraduationTenantProtection::protect('graduation_approvals');

        DB::statement('
            CREATE TABLE graduation.graduation_awards (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                enrollment_id BIGINT NOT NULL,
                student_id BIGINT NOT NULL,
                academic_year_id BIGINT NOT NULL,
                specialization_id BIGINT,
                current_issued_version_id BIGINT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                created_by BIGINT,
                CONSTRAINT graduation_awards_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT graduation_awards_student_fk
                    FOREIGN KEY (student_id) REFERENCES students.students(id) ON DELETE RESTRICT,
                CONSTRAINT graduation_awards_enrollment_school_fk
                    FOREIGN KEY (enrollment_id, school_id)
                    REFERENCES enrollment.enrollments(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT graduation_awards_enrollment_year_fk
                    FOREIGN KEY (enrollment_id, academic_year_id)
                    REFERENCES enrollment.enrollments(id, academic_year_id) ON DELETE RESTRICT,
                CONSTRAINT graduation_awards_school_enrollment_uq UNIQUE (school_id, enrollment_id)
            )
        ');
        GraduationTenantProtection::protect('graduation_awards');

        DB::statement('
            CREATE TABLE graduation.graduation_award_versions (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                graduation_award_id BIGINT NOT NULL,
                version_no INTEGER NOT NULL,
                graduation_approval_id BIGINT NOT NULL,
                completion_outcome_version_id BIGINT NOT NULL,
                lifecycle_status SMALLINT NOT NULL DEFAULT 1,
                is_current_issued BOOLEAN NOT NULL DEFAULT false,
                awarded_at TIMESTAMPTZ NOT NULL,
                issued_by BIGINT,
                award_number VARCHAR(50),
                honors_code SMALLINT,
                supersedes_version_id BIGINT,
                superseded_by_version_id BIGINT,
                source_fingerprint VARCHAR(128),
                correlation_id VARCHAR(64),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT graduation_award_versions_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT graduation_award_versions_award_fk
                    FOREIGN KEY (graduation_award_id) REFERENCES graduation.graduation_awards(id) ON DELETE RESTRICT,
                CONSTRAINT graduation_award_versions_approval_fk
                    FOREIGN KEY (graduation_approval_id) REFERENCES graduation.graduation_approvals(id) ON DELETE RESTRICT,
                CONSTRAINT graduation_award_versions_cov_fk
                    FOREIGN KEY (completion_outcome_version_id, school_id)
                    REFERENCES graduation.completion_outcome_versions(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT graduation_award_versions_version_chk CHECK (version_no >= 1),
                CONSTRAINT graduation_award_versions_lifecycle_chk CHECK (lifecycle_status BETWEEN 1 AND 3),
                CONSTRAINT graduation_award_versions_uq UNIQUE (graduation_award_id, version_no)
            )
        ');
        DB::statement('
            CREATE UNIQUE INDEX graduation_award_versions_current_issued_uidx
            ON graduation.graduation_award_versions (graduation_award_id)
            WHERE is_current_issued AND lifecycle_status = 1
        ');
        DB::statement('
            CREATE UNIQUE INDEX graduation_award_versions_award_number_uidx
            ON graduation.graduation_award_versions (school_id, award_number)
            WHERE award_number IS NOT NULL
        ');
        DB::statement('
            CREATE UNIQUE INDEX graduation_award_versions_id_school_uidx
            ON graduation.graduation_award_versions (id, school_id)
        ');
        DB::statement('
            ALTER TABLE graduation.graduation_award_versions
            ADD CONSTRAINT graduation_award_versions_supersedes_fk
                FOREIGN KEY (supersedes_version_id) REFERENCES graduation.graduation_award_versions(id) ON DELETE RESTRICT
        ');
        DB::statement('
            ALTER TABLE graduation.graduation_award_versions
            ADD CONSTRAINT graduation_award_versions_superseded_by_fk
                FOREIGN KEY (superseded_by_version_id) REFERENCES graduation.graduation_award_versions(id) ON DELETE RESTRICT
        ');
        GraduationTenantProtection::protect('graduation_award_versions');

        DB::statement('
            ALTER TABLE graduation.graduation_awards
            ADD CONSTRAINT graduation_awards_current_issued_fk
                FOREIGN KEY (current_issued_version_id)
                REFERENCES graduation.graduation_award_versions(id) ON DELETE RESTRICT
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE graduation.graduation_awards DROP CONSTRAINT IF EXISTS graduation_awards_current_issued_fk');
        GraduationTenantProtection::unprotect('graduation_award_versions');
        GraduationTenantProtection::unprotect('graduation_awards');
        GraduationTenantProtection::unprotect('graduation_approvals');
        DB::statement('DROP TABLE IF EXISTS graduation.graduation_award_versions');
        DB::statement('DROP TABLE IF EXISTS graduation.graduation_awards');
        DB::statement('DROP TABLE IF EXISTS graduation.graduation_approvals');
    }
};
