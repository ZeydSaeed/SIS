<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3C.12 M18 — Immutability reject-delete + denorm identity triggers (3C.11A remediation).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement("
            CREATE OR REPLACE FUNCTION graduation.reject_hard_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of %.% is forbidden', TG_TABLE_SCHEMA, TG_TABLE_NAME;
            END;
            $$
        ");

        $rejectDeleteTables = [
            'completion_outcomes',
            'completion_outcome_versions',
            'evidence_sets',
            'evidence_items',
            'requirement_evaluations',
            'graduation_approvals',
            'graduation_awards',
            'graduation_award_versions',
            'outcome_supersessions',
            'revocation_records',
            'eligibility_policy_versions',
            'requirement_definition_versions',
        ];

        foreach ($rejectDeleteTables as $table) {
            DB::statement("
                CREATE TRIGGER {$table}_reject_delete
                BEFORE DELETE ON graduation.{$table}
                FOR EACH ROW
                EXECUTE FUNCTION graduation.reject_hard_delete()
            ");
        }

        DB::statement("
            CREATE OR REPLACE FUNCTION graduation.enforce_completion_outcome_denorm()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                enr RECORD;
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    IF NEW.enrollment_id IS DISTINCT FROM OLD.enrollment_id
                        OR NEW.school_id IS DISTINCT FROM OLD.school_id
                        OR NEW.student_id IS DISTINCT FROM OLD.student_id
                        OR NEW.academic_year_id IS DISTINCT FROM OLD.academic_year_id
                        OR NEW.specialization_id IS DISTINCT FROM OLD.specialization_id THEN
                        RAISE EXCEPTION 'Identity columns on graduation.completion_outcomes are immutable';
                    END IF;
                    RETURN NEW;
                END IF;

                SELECT e.school_id, e.student_id, e.academic_year_id, e.specialization_id
                INTO enr
                FROM enrollment.enrollments e
                WHERE e.id = NEW.enrollment_id;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'Enrollment % not found for completion outcome denorm', NEW.enrollment_id;
                END IF;

                IF NEW.school_id IS DISTINCT FROM enr.school_id
                    OR NEW.student_id IS DISTINCT FROM enr.student_id
                    OR NEW.academic_year_id IS DISTINCT FROM enr.academic_year_id
                    OR NEW.specialization_id IS DISTINCT FROM enr.specialization_id THEN
                    RAISE EXCEPTION 'Denormalized identity on graduation.completion_outcomes must match enrollment';
                END IF;

                RETURN NEW;
            END;
            $$
        ");

        DB::statement('
            CREATE TRIGGER completion_outcomes_denorm_biu
            BEFORE INSERT OR UPDATE ON graduation.completion_outcomes
            FOR EACH ROW
            EXECUTE FUNCTION graduation.enforce_completion_outcome_denorm()
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION graduation.enforce_graduation_award_denorm()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                enr RECORD;
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    IF NEW.enrollment_id IS DISTINCT FROM OLD.enrollment_id
                        OR NEW.school_id IS DISTINCT FROM OLD.school_id
                        OR NEW.student_id IS DISTINCT FROM OLD.student_id
                        OR NEW.academic_year_id IS DISTINCT FROM OLD.academic_year_id
                        OR NEW.specialization_id IS DISTINCT FROM OLD.specialization_id THEN
                        RAISE EXCEPTION 'Identity columns on graduation.graduation_awards are immutable';
                    END IF;
                    RETURN NEW;
                END IF;

                SELECT e.school_id, e.student_id, e.academic_year_id, e.specialization_id
                INTO enr
                FROM enrollment.enrollments e
                WHERE e.id = NEW.enrollment_id;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'Enrollment % not found for graduation award denorm', NEW.enrollment_id;
                END IF;

                IF NEW.school_id IS DISTINCT FROM enr.school_id
                    OR NEW.student_id IS DISTINCT FROM enr.student_id
                    OR NEW.academic_year_id IS DISTINCT FROM enr.academic_year_id
                    OR NEW.specialization_id IS DISTINCT FROM enr.specialization_id THEN
                    RAISE EXCEPTION 'Denormalized identity on graduation.graduation_awards must match enrollment';
                END IF;

                RETURN NEW;
            END;
            $$
        ");

        DB::statement('
            CREATE TRIGGER graduation_awards_denorm_biu
            BEFORE INSERT OR UPDATE ON graduation.graduation_awards
            FOR EACH ROW
            EXECUTE FUNCTION graduation.enforce_graduation_award_denorm()
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION graduation.enforce_child_school_matches_completion_version()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                parent_school BIGINT;
            BEGIN
                SELECT v.school_id INTO parent_school
                FROM graduation.completion_outcome_versions v
                WHERE v.id = NEW.completion_outcome_version_id;

                IF NOT FOUND OR parent_school IS DISTINCT FROM NEW.school_id THEN
                    RAISE EXCEPTION 'school_id must match completion_outcome_versions.school_id';
                END IF;

                IF TG_OP = 'UPDATE' AND NEW.school_id IS DISTINCT FROM OLD.school_id THEN
                    RAISE EXCEPTION 'school_id is immutable on %', TG_TABLE_NAME;
                END IF;

                RETURN NEW;
            END;
            $$
        ");

        foreach (['evidence_sets', 'requirement_evaluations', 'graduation_approvals'] as $table) {
            DB::statement("
                CREATE TRIGGER {$table}_school_parent_biu
                BEFORE INSERT OR UPDATE ON graduation.{$table}
                FOR EACH ROW
                EXECUTE FUNCTION graduation.enforce_child_school_matches_completion_version()
            ");
        }

        DB::statement("
            CREATE OR REPLACE FUNCTION graduation.enforce_evidence_item_school()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                parent_school BIGINT;
            BEGIN
                SELECT s.school_id INTO parent_school
                FROM graduation.evidence_sets s
                WHERE s.id = NEW.evidence_set_id;

                IF NOT FOUND OR parent_school IS DISTINCT FROM NEW.school_id THEN
                    RAISE EXCEPTION 'school_id must match evidence_sets.school_id';
                END IF;

                IF TG_OP = 'UPDATE' AND NEW.school_id IS DISTINCT FROM OLD.school_id THEN
                    RAISE EXCEPTION 'school_id is immutable on evidence_items';
                END IF;

                RETURN NEW;
            END;
            $$
        ");

        DB::statement('
            CREATE TRIGGER evidence_items_school_parent_biu
            BEFORE INSERT OR UPDATE ON graduation.evidence_items
            FOR EACH ROW
            EXECUTE FUNCTION graduation.enforce_evidence_item_school()
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION graduation.enforce_award_version_school()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                parent_school BIGINT;
            BEGIN
                SELECT a.school_id INTO parent_school
                FROM graduation.graduation_awards a
                WHERE a.id = NEW.graduation_award_id;

                IF NOT FOUND OR parent_school IS DISTINCT FROM NEW.school_id THEN
                    RAISE EXCEPTION 'school_id must match graduation_awards.school_id';
                END IF;

                IF TG_OP = 'UPDATE' AND NEW.school_id IS DISTINCT FROM OLD.school_id THEN
                    RAISE EXCEPTION 'school_id is immutable on graduation_award_versions';
                END IF;

                RETURN NEW;
            END;
            $$
        ");

        DB::statement('
            CREATE TRIGGER graduation_award_versions_school_parent_biu
            BEFORE INSERT OR UPDATE ON graduation.graduation_award_versions
            FOR EACH ROW
            EXECUTE FUNCTION graduation.enforce_award_version_school()
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION graduation.enforce_official_completion_version_immutability()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF OLD.lifecycle_status = 2 THEN
                    IF NEW.evaluation_status IS DISTINCT FROM OLD.evaluation_status
                        OR NEW.eligibility_status IS DISTINCT FROM OLD.eligibility_status
                        OR NEW.eligibility_policy_version_id IS DISTINCT FROM OLD.eligibility_policy_version_id
                        OR NEW.calculation_version IS DISTINCT FROM OLD.calculation_version
                        OR NEW.source_fingerprint IS DISTINCT FROM OLD.source_fingerprint
                        OR NEW.policy_fingerprint IS DISTINCT FROM OLD.policy_fingerprint
                        OR NEW.evaluated_at IS DISTINCT FROM OLD.evaluated_at
                        OR NEW.eligibility_determined_at IS DISTINCT FROM OLD.eligibility_determined_at
                        OR NEW.version_no IS DISTINCT FROM OLD.version_no
                        OR NEW.completion_outcome_id IS DISTINCT FROM OLD.completion_outcome_id
                        OR NEW.school_id IS DISTINCT FROM OLD.school_id
                        OR NEW.supersedes_version_id IS DISTINCT FROM OLD.supersedes_version_id THEN
                        RAISE EXCEPTION 'Official completion_outcome_versions payload is immutable';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$
        ");

        DB::statement('
            CREATE TRIGGER completion_outcome_versions_official_immutable
            BEFORE UPDATE ON graduation.completion_outcome_versions
            FOR EACH ROW
            EXECUTE FUNCTION graduation.enforce_official_completion_version_immutability()
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION graduation.enforce_issued_award_version_immutability()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF OLD.lifecycle_status = 1 THEN
                    IF NEW.graduation_award_id IS DISTINCT FROM OLD.graduation_award_id
                        OR NEW.version_no IS DISTINCT FROM OLD.version_no
                        OR NEW.graduation_approval_id IS DISTINCT FROM OLD.graduation_approval_id
                        OR NEW.completion_outcome_version_id IS DISTINCT FROM OLD.completion_outcome_version_id
                        OR NEW.awarded_at IS DISTINCT FROM OLD.awarded_at
                        OR NEW.award_number IS DISTINCT FROM OLD.award_number
                        OR NEW.honors_code IS DISTINCT FROM OLD.honors_code
                        OR NEW.school_id IS DISTINCT FROM OLD.school_id
                        OR NEW.supersedes_version_id IS DISTINCT FROM OLD.supersedes_version_id THEN
                        RAISE EXCEPTION 'Issued graduation_award_versions payload is immutable';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$
        ");

        DB::statement('
            CREATE TRIGGER graduation_award_versions_issued_immutable
            BEFORE UPDATE ON graduation.graduation_award_versions
            FOR EACH ROW
            EXECUTE FUNCTION graduation.enforce_issued_award_version_immutability()
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        $drops = [
            ['completion_outcomes', 'completion_outcomes_reject_delete'],
            ['completion_outcome_versions', 'completion_outcome_versions_reject_delete'],
            ['evidence_sets', 'evidence_sets_reject_delete'],
            ['evidence_items', 'evidence_items_reject_delete'],
            ['requirement_evaluations', 'requirement_evaluations_reject_delete'],
            ['graduation_approvals', 'graduation_approvals_reject_delete'],
            ['graduation_awards', 'graduation_awards_reject_delete'],
            ['graduation_award_versions', 'graduation_award_versions_reject_delete'],
            ['outcome_supersessions', 'outcome_supersessions_reject_delete'],
            ['revocation_records', 'revocation_records_reject_delete'],
            ['eligibility_policy_versions', 'eligibility_policy_versions_reject_delete'],
            ['requirement_definition_versions', 'requirement_definition_versions_reject_delete'],
            ['completion_outcomes', 'completion_outcomes_denorm_biu'],
            ['graduation_awards', 'graduation_awards_denorm_biu'],
            ['evidence_sets', 'evidence_sets_school_parent_biu'],
            ['requirement_evaluations', 'requirement_evaluations_school_parent_biu'],
            ['graduation_approvals', 'graduation_approvals_school_parent_biu'],
            ['evidence_items', 'evidence_items_school_parent_biu'],
            ['graduation_award_versions', 'graduation_award_versions_school_parent_biu'],
            ['completion_outcome_versions', 'completion_outcome_versions_official_immutable'],
            ['graduation_award_versions', 'graduation_award_versions_issued_immutable'],
        ];

        foreach ($drops as [$table, $trigger]) {
            DB::statement("DROP TRIGGER IF EXISTS {$trigger} ON graduation.{$table}");
        }

        DB::statement('DROP FUNCTION IF EXISTS graduation.enforce_issued_award_version_immutability()');
        DB::statement('DROP FUNCTION IF EXISTS graduation.enforce_official_completion_version_immutability()');
        DB::statement('DROP FUNCTION IF EXISTS graduation.enforce_award_version_school()');
        DB::statement('DROP FUNCTION IF EXISTS graduation.enforce_evidence_item_school()');
        DB::statement('DROP FUNCTION IF EXISTS graduation.enforce_child_school_matches_completion_version()');
        DB::statement('DROP FUNCTION IF EXISTS graduation.enforce_graduation_award_denorm()');
        DB::statement('DROP FUNCTION IF EXISTS graduation.enforce_completion_outcome_denorm()');
        DB::statement('DROP FUNCTION IF EXISTS graduation.reject_hard_delete()');
    }
};
