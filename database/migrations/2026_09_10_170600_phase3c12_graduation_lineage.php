<?php

use App\Database\GraduationTenantProtection;
use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3C.12 M14–M15 — Supersession + revocation (Option A RLS).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            CREATE TABLE graduation.outcome_supersessions (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                lineage_kind SMALLINT NOT NULL,
                predecessor_completion_version_id BIGINT,
                successor_completion_version_id BIGINT,
                predecessor_award_version_id BIGINT,
                successor_award_version_id BIGINT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                created_by BIGINT,
                correlation_id VARCHAR(64),
                CONSTRAINT outcome_supersessions_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT outcome_supersessions_kind_chk CHECK (lineage_kind IN (1, 2)),
                CONSTRAINT outcome_supersessions_endpoints_chk CHECK (
                    (
                        lineage_kind = 1
                        AND predecessor_completion_version_id IS NOT NULL
                        AND successor_completion_version_id IS NOT NULL
                        AND predecessor_completion_version_id <> successor_completion_version_id
                        AND predecessor_award_version_id IS NULL
                        AND successor_award_version_id IS NULL
                    )
                    OR (
                        lineage_kind = 2
                        AND predecessor_award_version_id IS NOT NULL
                        AND successor_award_version_id IS NOT NULL
                        AND predecessor_award_version_id <> successor_award_version_id
                        AND predecessor_completion_version_id IS NULL
                        AND successor_completion_version_id IS NULL
                    )
                ),
                CONSTRAINT outcome_supersessions_pred_cov_fk
                    FOREIGN KEY (predecessor_completion_version_id, school_id)
                    REFERENCES graduation.completion_outcome_versions(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT outcome_supersessions_succ_cov_fk
                    FOREIGN KEY (successor_completion_version_id, school_id)
                    REFERENCES graduation.completion_outcome_versions(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT outcome_supersessions_pred_gav_fk
                    FOREIGN KEY (predecessor_award_version_id, school_id)
                    REFERENCES graduation.graduation_award_versions(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT outcome_supersessions_succ_gav_fk
                    FOREIGN KEY (successor_award_version_id, school_id)
                    REFERENCES graduation.graduation_award_versions(id, school_id) ON DELETE RESTRICT
            )
        ');
        GraduationTenantProtection::protect('outcome_supersessions');

        DB::statement('
            CREATE TABLE graduation.revocation_records (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                graduation_award_version_id BIGINT NOT NULL,
                revoked_at TIMESTAMPTZ NOT NULL,
                revoked_by BIGINT,
                revocation_reason_ref VARCHAR(128),
                correlation_id VARCHAR(64),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT revocation_records_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT revocation_records_gav_fk
                    FOREIGN KEY (graduation_award_version_id, school_id)
                    REFERENCES graduation.graduation_award_versions(id, school_id) ON DELETE RESTRICT
            )
        ');
        GraduationTenantProtection::protect('revocation_records');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        GraduationTenantProtection::unprotect('revocation_records');
        GraduationTenantProtection::unprotect('outcome_supersessions');
        DB::statement('DROP TABLE IF EXISTS graduation.revocation_records');
        DB::statement('DROP TABLE IF EXISTS graduation.outcome_supersessions');
    }
};
