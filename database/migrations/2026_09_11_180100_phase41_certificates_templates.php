<?php

use App\Database\CertificatesTenantProtection;
use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4.1 — Certificates templates + template versions (school-scoped, RLS FORCE).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            CREATE TABLE certificates.certificate_templates (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                certificate_type SMALLINT NOT NULL,
                name VARCHAR(255) NOT NULL,
                status SMALLINT NOT NULL DEFAULT 1,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                created_by BIGINT,
                CONSTRAINT certificate_templates_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT certificate_templates_type_chk CHECK (certificate_type >= 1),
                CONSTRAINT certificate_templates_status_chk CHECK (status BETWEEN 1 AND 3),
                CONSTRAINT certificate_templates_school_type_name_uq UNIQUE (school_id, certificate_type, name)
            )
        ');
        DB::statement('
            CREATE UNIQUE INDEX certificate_templates_id_school_uidx
            ON certificates.certificate_templates (id, school_id)
        ');
        CertificatesTenantProtection::protect('certificate_templates');

        DB::statement('
            CREATE TABLE certificates.certificate_template_versions (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                template_id BIGINT NOT NULL,
                version_no INTEGER NOT NULL,
                locale VARCHAR(16),
                content_hash VARCHAR(128) NOT NULL,
                template_storage_key VARCHAR(500) NOT NULL,
                effective_from TIMESTAMPTZ,
                effective_to TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                created_by BIGINT,
                CONSTRAINT certificate_template_versions_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT certificate_template_versions_template_school_fk
                    FOREIGN KEY (template_id, school_id)
                    REFERENCES certificates.certificate_templates(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT certificate_template_versions_version_chk CHECK (version_no >= 1),
                CONSTRAINT certificate_template_versions_uq UNIQUE (template_id, version_no)
            )
        ');
        DB::statement('
            CREATE UNIQUE INDEX certificate_template_versions_id_school_uidx
            ON certificates.certificate_template_versions (id, school_id)
        ');
        CertificatesTenantProtection::protect('certificate_template_versions');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        CertificatesTenantProtection::unprotect('certificate_template_versions');
        CertificatesTenantProtection::unprotect('certificate_templates');
        DB::statement('DROP TABLE IF EXISTS certificates.certificate_template_versions');
        DB::statement('DROP TABLE IF EXISTS certificates.certificate_templates');
    }
};
