<?php

use App\Database\GraduationTenantProtection;
use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3C.12 M08–M10 — Evidence + requirement evaluations (Option A RLS).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            CREATE TABLE graduation.evidence_sets (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                completion_outcome_version_id BIGINT NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                created_by BIGINT,
                CONSTRAINT evidence_sets_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT evidence_sets_version_fk
                    FOREIGN KEY (completion_outcome_version_id, school_id)
                    REFERENCES graduation.completion_outcome_versions(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT evidence_sets_version_uq UNIQUE (completion_outcome_version_id)
            )
        ');
        GraduationTenantProtection::protect('evidence_sets');

        DB::statement('
            CREATE TABLE graduation.evidence_items (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                evidence_set_id BIGINT NOT NULL,
                source_type SMALLINT NOT NULL,
                source_id BIGINT NOT NULL,
                source_version_ref VARCHAR(128) NOT NULL DEFAULT \'\',
                academic_year_id BIGINT,
                inclusion_status SMALLINT NOT NULL,
                exclusion_reason_code SMALLINT,
                captured_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                captured_by BIGINT,
                CONSTRAINT evidence_items_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT evidence_items_set_fk
                    FOREIGN KEY (evidence_set_id) REFERENCES graduation.evidence_sets(id) ON DELETE RESTRICT,
                CONSTRAINT evidence_items_inclusion_chk CHECK (inclusion_status IN (1, 2)),
                CONSTRAINT evidence_items_exclusion_reason_chk CHECK (
                    (inclusion_status = 1 AND exclusion_reason_code IS NULL)
                    OR (inclusion_status = 2 AND exclusion_reason_code IS NOT NULL)
                ),
                CONSTRAINT evidence_items_source_uq UNIQUE (
                    evidence_set_id, source_type, source_id, source_version_ref
                )
            )
        ');
        GraduationTenantProtection::protect('evidence_items');

        DB::statement('
            CREATE TABLE graduation.requirement_evaluations (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                completion_outcome_version_id BIGINT NOT NULL,
                requirement_definition_version_id BIGINT NOT NULL,
                result_status SMALLINT NOT NULL,
                evaluated_at TIMESTAMPTZ NOT NULL,
                notes_ref VARCHAR(255),
                CONSTRAINT requirement_evaluations_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT requirement_evaluations_cov_fk
                    FOREIGN KEY (completion_outcome_version_id, school_id)
                    REFERENCES graduation.completion_outcome_versions(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT requirement_evaluations_req_fk
                    FOREIGN KEY (requirement_definition_version_id, school_id)
                    REFERENCES graduation.requirement_definition_versions(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT requirement_evaluations_result_chk CHECK (result_status BETWEEN 1 AND 6),
                CONSTRAINT requirement_evaluations_uq UNIQUE (
                    completion_outcome_version_id, requirement_definition_version_id
                )
            )
        ');
        GraduationTenantProtection::protect('requirement_evaluations');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        GraduationTenantProtection::unprotect('requirement_evaluations');
        GraduationTenantProtection::unprotect('evidence_items');
        GraduationTenantProtection::unprotect('evidence_sets');
        DB::statement('DROP TABLE IF EXISTS graduation.requirement_evaluations');
        DB::statement('DROP TABLE IF EXISTS graduation.evidence_items');
        DB::statement('DROP TABLE IF EXISTS graduation.evidence_sets');
    }
};
