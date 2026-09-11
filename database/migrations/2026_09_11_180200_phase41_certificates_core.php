<?php

use App\Database\CertificatesTenantProtection;
use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4.1 — Certificates identity, issuances, artifacts, generation jobs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            CREATE TABLE certificates.certificates (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                enrollment_id BIGINT NOT NULL,
                student_id BIGINT NOT NULL,
                certificate_type SMALLINT NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                created_by BIGINT,
                CONSTRAINT certificates_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT certificates_enrollment_school_fk
                    FOREIGN KEY (enrollment_id, school_id)
                    REFERENCES enrollment.enrollments(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT certificates_student_fk
                    FOREIGN KEY (student_id) REFERENCES students.students(id) ON DELETE RESTRICT,
                CONSTRAINT certificates_type_chk CHECK (certificate_type >= 1),
                CONSTRAINT certificates_school_enrollment_type_uq UNIQUE (school_id, enrollment_id, certificate_type)
            )
        ');
        DB::statement('
            CREATE UNIQUE INDEX certificates_id_school_uidx
            ON certificates.certificates (id, school_id)
        ');
        CertificatesTenantProtection::protect('certificates');

        DB::statement('
            CREATE TABLE certificates.certificate_issuances (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                certificate_id BIGINT NOT NULL,
                issuance_no INTEGER NOT NULL,
                graduation_award_version_id BIGINT NOT NULL,
                graduation_award_id BIGINT NOT NULL,
                enrollment_id BIGINT NOT NULL,
                template_version_id BIGINT NOT NULL,
                lifecycle_status SMALLINT NOT NULL DEFAULT 1,
                certificate_number VARCHAR(50) NOT NULL,
                verification_code VARCHAR(128) NOT NULL,
                supersedes_issuance_id BIGINT,
                issued_at TIMESTAMPTZ,
                issued_by BIGINT,
                revoked_at TIMESTAMPTZ,
                revoked_by BIGINT,
                revoke_reason_ref VARCHAR(128),
                correlation_id VARCHAR(64),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                created_by BIGINT,
                CONSTRAINT certificate_issuances_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT certificate_issuances_certificate_school_fk
                    FOREIGN KEY (certificate_id, school_id)
                    REFERENCES certificates.certificates(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT certificate_issuances_award_version_school_fk
                    FOREIGN KEY (graduation_award_version_id, school_id)
                    REFERENCES graduation.graduation_award_versions(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT certificate_issuances_award_fk
                    FOREIGN KEY (graduation_award_id)
                    REFERENCES graduation.graduation_awards(id) ON DELETE RESTRICT,
                CONSTRAINT certificate_issuances_enrollment_school_fk
                    FOREIGN KEY (enrollment_id, school_id)
                    REFERENCES enrollment.enrollments(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT certificate_issuances_template_version_school_fk
                    FOREIGN KEY (template_version_id, school_id)
                    REFERENCES certificates.certificate_template_versions(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT certificate_issuances_issuance_no_chk CHECK (issuance_no >= 1),
                CONSTRAINT certificate_issuances_lifecycle_chk CHECK (lifecycle_status BETWEEN 1 AND 5),
                CONSTRAINT certificate_issuances_certificate_issuance_uq UNIQUE (certificate_id, issuance_no),
                CONSTRAINT certificate_issuances_school_number_uq UNIQUE (school_id, certificate_number),
                CONSTRAINT certificate_issuances_verification_code_uq UNIQUE (verification_code)
            )
        ');
        DB::statement('
            CREATE UNIQUE INDEX certificate_issuances_id_school_uidx
            ON certificates.certificate_issuances (id, school_id)
        ');
        DB::statement('
            ALTER TABLE certificates.certificate_issuances
            ADD CONSTRAINT certificate_issuances_supersedes_school_fk
                FOREIGN KEY (supersedes_issuance_id, school_id)
                REFERENCES certificates.certificate_issuances(id, school_id) ON DELETE RESTRICT
        ');
        CertificatesTenantProtection::protect('certificate_issuances');

        DB::statement('
            CREATE TABLE certificates.certificate_artifacts (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                issuance_id BIGINT NOT NULL,
                attempt_no INTEGER NOT NULL,
                storage_key VARCHAR(500) NOT NULL,
                content_type VARCHAR(100) NOT NULL,
                file_hash VARCHAR(64) NOT NULL,
                byte_size BIGINT NOT NULL,
                generated_at TIMESTAMPTZ NOT NULL,
                generator_version VARCHAR(64) NOT NULL,
                is_current BOOLEAN NOT NULL DEFAULT false,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT certificate_artifacts_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT certificate_artifacts_issuance_school_fk
                    FOREIGN KEY (issuance_id, school_id)
                    REFERENCES certificates.certificate_issuances(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT certificate_artifacts_attempt_chk CHECK (attempt_no >= 1),
                CONSTRAINT certificate_artifacts_byte_size_chk CHECK (byte_size >= 0),
                CONSTRAINT certificate_artifacts_issuance_attempt_uq UNIQUE (issuance_id, attempt_no)
            )
        ');
        DB::statement('
            CREATE UNIQUE INDEX certificate_artifacts_current_uidx
            ON certificates.certificate_artifacts (issuance_id)
            WHERE is_current
        ');
        DB::statement('
            CREATE UNIQUE INDEX certificate_artifacts_id_school_uidx
            ON certificates.certificate_artifacts (id, school_id)
        ');
        CertificatesTenantProtection::protect('certificate_artifacts');

        DB::statement('
            CREATE TABLE certificates.certificate_generation_jobs (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                issuance_id BIGINT NOT NULL,
                job_status SMALLINT NOT NULL DEFAULT 1,
                attempt_count INTEGER NOT NULL DEFAULT 0,
                last_error_ref VARCHAR(255),
                correlation_id VARCHAR(64),
                queued_at TIMESTAMPTZ,
                started_at TIMESTAMPTZ,
                finished_at TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT certificate_generation_jobs_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT certificate_generation_jobs_issuance_school_fk
                    FOREIGN KEY (issuance_id, school_id)
                    REFERENCES certificates.certificate_issuances(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT certificate_generation_jobs_status_chk CHECK (job_status BETWEEN 1 AND 4),
                CONSTRAINT certificate_generation_jobs_attempt_chk CHECK (attempt_count >= 0),
                CONSTRAINT certificate_generation_jobs_issuance_uq UNIQUE (issuance_id)
            )
        ');
        DB::statement('
            CREATE UNIQUE INDEX certificate_generation_jobs_id_school_uidx
            ON certificates.certificate_generation_jobs (id, school_id)
        ');
        CertificatesTenantProtection::protect('certificate_generation_jobs');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        CertificatesTenantProtection::unprotect('certificate_generation_jobs');
        CertificatesTenantProtection::unprotect('certificate_artifacts');
        CertificatesTenantProtection::unprotect('certificate_issuances');
        CertificatesTenantProtection::unprotect('certificates');

        DB::statement('DROP TABLE IF EXISTS certificates.certificate_generation_jobs');
        DB::statement('DROP TABLE IF EXISTS certificates.certificate_artifacts');
        DB::statement('ALTER TABLE certificates.certificate_issuances DROP CONSTRAINT IF EXISTS certificate_issuances_supersedes_school_fk');
        DB::statement('DROP TABLE IF EXISTS certificates.certificate_issuances');
        DB::statement('DROP TABLE IF EXISTS certificates.certificates');
    }
};
