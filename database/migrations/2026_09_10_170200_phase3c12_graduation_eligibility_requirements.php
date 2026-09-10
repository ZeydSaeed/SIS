<?php

use App\Database\GraduationTenantProtection;
use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3C.12 M02–M05 — Eligibility policies + requirement definitions (Option A RLS).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            CREATE TABLE graduation.eligibility_policies (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                policy_code VARCHAR(64) NOT NULL,
                name VARCHAR(255) NOT NULL,
                status SMALLINT NOT NULL DEFAULT 1,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                created_by BIGINT,
                CONSTRAINT eligibility_policies_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT eligibility_policies_status_chk CHECK (status BETWEEN 1 AND 5),
                CONSTRAINT eligibility_policies_school_code_uq UNIQUE (school_id, policy_code)
            )
        ');
        GraduationTenantProtection::protect('eligibility_policies');

        DB::statement('
            CREATE TABLE graduation.eligibility_policy_versions (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                eligibility_policy_id BIGINT NOT NULL,
                version_no INTEGER NOT NULL,
                lifecycle_status SMALLINT NOT NULL DEFAULT 1,
                is_current_effective BOOLEAN NOT NULL DEFAULT false,
                effective_from TIMESTAMPTZ,
                effective_to TIMESTAMPTZ,
                content_payload JSONB,
                published_at TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                created_by BIGINT,
                CONSTRAINT eligibility_policy_versions_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT eligibility_policy_versions_policy_fk
                    FOREIGN KEY (eligibility_policy_id) REFERENCES graduation.eligibility_policies(id) ON DELETE RESTRICT,
                CONSTRAINT eligibility_policy_versions_version_chk CHECK (version_no >= 1),
                CONSTRAINT eligibility_policy_versions_lifecycle_chk CHECK (lifecycle_status BETWEEN 1 AND 4),
                CONSTRAINT eligibility_policy_versions_effective_chk CHECK (
                    effective_from IS NULL OR effective_to IS NULL OR effective_from <= effective_to
                ),
                CONSTRAINT eligibility_policy_versions_uq UNIQUE (eligibility_policy_id, version_no)
            )
        ');
        DB::statement('
            CREATE UNIQUE INDEX eligibility_policy_versions_current_effective_uidx
            ON graduation.eligibility_policy_versions (eligibility_policy_id)
            WHERE is_current_effective
        ');
        DB::statement('
            CREATE UNIQUE INDEX eligibility_policy_versions_id_school_uidx
            ON graduation.eligibility_policy_versions (id, school_id)
        ');
        GraduationTenantProtection::protect('eligibility_policy_versions');

        DB::statement('
            CREATE TABLE graduation.requirement_definitions (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                eligibility_policy_id BIGINT NOT NULL,
                requirement_code VARCHAR(64) NOT NULL,
                name VARCHAR(255) NOT NULL,
                status SMALLINT NOT NULL DEFAULT 1,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                created_by BIGINT,
                CONSTRAINT requirement_definitions_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT requirement_definitions_policy_fk
                    FOREIGN KEY (eligibility_policy_id) REFERENCES graduation.eligibility_policies(id) ON DELETE RESTRICT,
                CONSTRAINT requirement_definitions_status_chk CHECK (status BETWEEN 1 AND 5),
                CONSTRAINT requirement_definitions_code_uq UNIQUE (eligibility_policy_id, requirement_code)
            )
        ');
        GraduationTenantProtection::protect('requirement_definitions');

        DB::statement('
            CREATE TABLE graduation.requirement_definition_versions (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                requirement_definition_id BIGINT NOT NULL,
                version_no INTEGER NOT NULL,
                lifecycle_status SMALLINT NOT NULL DEFAULT 1,
                unit_kind SMALLINT NOT NULL DEFAULT 1,
                rule_payload JSONB,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                created_by BIGINT,
                CONSTRAINT requirement_definition_versions_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT requirement_definition_versions_def_fk
                    FOREIGN KEY (requirement_definition_id) REFERENCES graduation.requirement_definitions(id) ON DELETE RESTRICT,
                CONSTRAINT requirement_definition_versions_version_chk CHECK (version_no >= 1),
                CONSTRAINT requirement_definition_versions_lifecycle_chk CHECK (lifecycle_status BETWEEN 1 AND 4),
                CONSTRAINT requirement_definition_versions_uq UNIQUE (requirement_definition_id, version_no)
            )
        ');
        DB::statement('
            CREATE UNIQUE INDEX requirement_definition_versions_id_school_uidx
            ON graduation.requirement_definition_versions (id, school_id)
        ');
        GraduationTenantProtection::protect('requirement_definition_versions');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        GraduationTenantProtection::unprotect('requirement_definition_versions');
        GraduationTenantProtection::unprotect('requirement_definitions');
        GraduationTenantProtection::unprotect('eligibility_policy_versions');
        GraduationTenantProtection::unprotect('eligibility_policies');

        DB::statement('DROP TABLE IF EXISTS graduation.requirement_definition_versions');
        DB::statement('DROP TABLE IF EXISTS graduation.requirement_definitions');
        DB::statement('DROP TABLE IF EXISTS graduation.eligibility_policy_versions');
        DB::statement('DROP TABLE IF EXISTS graduation.eligibility_policies');
    }
};
