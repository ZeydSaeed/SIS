<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3C.12 M16 — Supporting indexes (REQUIRED / RECOMMENDED from INDEX plan).
 * Partial UNIQUEs for current flags already created with tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            CREATE INDEX IF NOT EXISTS completion_outcomes_school_year_idx
            ON graduation.completion_outcomes (school_id, academic_year_id)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS completion_outcomes_student_school_idx
            ON graduation.completion_outcomes (student_id, school_id)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS completion_outcome_versions_school_status_idx
            ON graduation.completion_outcome_versions (school_id, eligibility_status, evaluated_at)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS completion_outcome_versions_policy_idx
            ON graduation.completion_outcome_versions (eligibility_policy_version_id)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS requirement_evaluations_version_status_idx
            ON graduation.requirement_evaluations (completion_outcome_version_id, result_status)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS evidence_items_source_lookup_idx
            ON graduation.evidence_items (school_id, source_type, source_id)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS graduation_awards_school_year_idx
            ON graduation.graduation_awards (school_id, academic_year_id)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS graduation_award_versions_school_awarded_idx
            ON graduation.graduation_award_versions (school_id, awarded_at)
            WHERE lifecycle_status = 1
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS graduation_approvals_school_status_idx
            ON graduation.graduation_approvals (school_id, decision_status, requested_at)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS graduation_approvals_version_idx
            ON graduation.graduation_approvals (completion_outcome_version_id)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS revocation_records_award_version_idx
            ON graduation.revocation_records (graduation_award_version_id)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS outcome_supersessions_pred_succ_cov_idx
            ON graduation.outcome_supersessions (predecessor_completion_version_id, successor_completion_version_id)
            WHERE lineage_kind = 1
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS eligibility_policy_versions_effective_idx
            ON graduation.eligibility_policy_versions (eligibility_policy_id, effective_from, effective_to)
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        $indexes = [
            'completion_outcomes_school_year_idx',
            'completion_outcomes_student_school_idx',
            'completion_outcome_versions_school_status_idx',
            'completion_outcome_versions_policy_idx',
            'requirement_evaluations_version_status_idx',
            'evidence_items_source_lookup_idx',
            'graduation_awards_school_year_idx',
            'graduation_award_versions_school_awarded_idx',
            'graduation_approvals_school_status_idx',
            'graduation_approvals_version_idx',
            'revocation_records_award_version_idx',
            'outcome_supersessions_pred_succ_cov_idx',
            'eligibility_policy_versions_effective_idx',
        ];

        foreach ($indexes as $index) {
            DB::statement("DROP INDEX IF EXISTS graduation.{$index}");
        }
    }
};
