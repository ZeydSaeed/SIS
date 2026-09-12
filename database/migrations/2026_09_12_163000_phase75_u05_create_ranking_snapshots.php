<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 7.5-U05 — ranking snapshots (comparative projection, NOT academic SSOT).
 * Header + entry rows; dense-rank ready.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement("
            CREATE TABLE results.ranking_snapshots (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                academic_year_id BIGINT NOT NULL,
                class_id BIGINT NOT NULL,
                snapshot_version INT NOT NULL,
                lifecycle_status SMALLINT NOT NULL,
                is_current BOOLEAN NOT NULL DEFAULT false,
                metric_code VARCHAR(32) NOT NULL DEFAULT 'YEAR_GPA_PERCENT_100',
                participant_count INT NOT NULL DEFAULT 0,
                source_fingerprint VARCHAR(128) NOT NULL,
                policy_pin JSONB NOT NULL DEFAULT '{}'::jsonb,
                calculated_at TIMESTAMPTZ NOT NULL,
                superseded_at TIMESTAMPTZ,
                correlation_id VARCHAR(64),
                created_by BIGINT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT ranking_snapshots_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT ranking_snapshots_year_fk
                    FOREIGN KEY (academic_year_id) REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                CONSTRAINT ranking_snapshots_class_fk
                    FOREIGN KEY (class_id) REFERENCES enrollment.classes(id) ON DELETE RESTRICT,
                CONSTRAINT ranking_snapshots_created_by_fk
                    FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL
            )
        ");

        DB::statement('
            ALTER TABLE results.ranking_snapshots
            ADD CONSTRAINT ranking_snapshots_lifecycle_check
            CHECK (lifecycle_status BETWEEN 1 AND 3)
        ');
        DB::statement('
            ALTER TABLE results.ranking_snapshots
            ADD CONSTRAINT ranking_snapshots_version_check
            CHECK (snapshot_version >= 1)
        ');
        DB::statement("
            ALTER TABLE results.ranking_snapshots
            ADD CONSTRAINT ranking_snapshots_metric_check
            CHECK (metric_code = 'YEAR_GPA_PERCENT_100')
        ");
        DB::statement('
            ALTER TABLE results.ranking_snapshots
            ADD CONSTRAINT ranking_snapshots_superseded_not_current_check
            CHECK (lifecycle_status <> 3 OR is_current = false)
        ');

        DB::statement('
            CREATE UNIQUE INDEX ranking_snapshots_identity_version_uidx
            ON results.ranking_snapshots (school_id, academic_year_id, class_id, snapshot_version)
        ');
        DB::statement('
            CREATE UNIQUE INDEX ranking_snapshots_current_uidx
            ON results.ranking_snapshots (school_id, academic_year_id, class_id)
            WHERE is_current
        ');

        DB::statement("
            CREATE TABLE results.ranking_snapshot_entries (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                ranking_snapshot_id BIGINT NOT NULL,
                school_id BIGINT NOT NULL,
                enrollment_id BIGINT NOT NULL,
                student_id BIGINT NOT NULL,
                gpa_result_id BIGINT NOT NULL,
                metric_value NUMERIC(8,2),
                rank_position INT NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT ranking_snapshot_entries_snapshot_fk
                    FOREIGN KEY (ranking_snapshot_id)
                    REFERENCES results.ranking_snapshots(id) ON DELETE RESTRICT,
                CONSTRAINT ranking_snapshot_entries_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT ranking_snapshot_entries_student_fk
                    FOREIGN KEY (student_id) REFERENCES students.students(id) ON DELETE RESTRICT,
                CONSTRAINT ranking_snapshot_entries_gpa_fk
                    FOREIGN KEY (gpa_result_id) REFERENCES results.gpa_results(id) ON DELETE RESTRICT
            )
        ");

        DB::statement('
            ALTER TABLE results.ranking_snapshot_entries
            ADD CONSTRAINT ranking_snapshot_entries_rank_check
            CHECK (rank_position >= 1)
        ');
        DB::statement('
            CREATE UNIQUE INDEX ranking_snapshot_entries_snapshot_enrollment_uidx
            ON results.ranking_snapshot_entries (ranking_snapshot_id, enrollment_id)
        ');
        DB::statement('
            CREATE INDEX ranking_snapshot_entries_snapshot_rank_idx
            ON results.ranking_snapshot_entries (ranking_snapshot_id, rank_position)
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION results.reject_ranking_snapshots_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of results.ranking_snapshots is forbidden';
            END; $$
        ");
        DB::statement('
            CREATE TRIGGER ranking_snapshots_reject_delete
            BEFORE DELETE ON results.ranking_snapshots
            FOR EACH ROW EXECUTE FUNCTION results.reject_ranking_snapshots_delete()
        ');
        DB::statement("
            CREATE OR REPLACE FUNCTION results.reject_ranking_snapshot_entries_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of results.ranking_snapshot_entries is forbidden';
            END; $$
        ");
        DB::statement('
            CREATE TRIGGER ranking_snapshot_entries_reject_delete
            BEFORE DELETE ON results.ranking_snapshot_entries
            FOR EACH ROW EXECUTE FUNCTION results.reject_ranking_snapshot_entries_delete()
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS ranking_snapshot_entries_reject_delete ON results.ranking_snapshot_entries');
        DB::statement('DROP FUNCTION IF EXISTS results.reject_ranking_snapshot_entries_delete()');
        DB::statement('DROP TABLE IF EXISTS results.ranking_snapshot_entries');
        DB::statement('DROP TRIGGER IF EXISTS ranking_snapshots_reject_delete ON results.ranking_snapshots');
        DB::statement('DROP FUNCTION IF EXISTS results.reject_ranking_snapshots_delete()');
        DB::statement('DROP TABLE IF EXISTS results.ranking_snapshots');
    }
};
