<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Timetable engine — lifecycle side (decisions D2, D4).
 *
 * - generation_runs  one solver run: mode, scope, options, input snapshot (+ fingerprint), result, quality.
 *                    Partial UNIQUE (school, year) while queued/running → never two concurrent runs.
 * - versions         a school-year timetable snapshot: draft → review (workflow.approval_requests) → approved
 *                    / rejected → published (effective dates) → superseded / archived. `schedules` stays the
 *                    working grid; published versions never change.
 * - version_entries  the snapshot rows — immutable (UPDATE and DELETE rejected by trigger).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement("
            CREATE TABLE timetable.generation_runs (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                academic_year_id BIGINT NOT NULL REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                mode SMALLINT NOT NULL CHECK (mode BETWEEN 1 AND 6),
                status SMALLINT NOT NULL DEFAULT 1 CHECK (status BETWEEN 1 AND 7),
                is_what_if BOOLEAN NOT NULL DEFAULT FALSE,
                scope JSONB NOT NULL DEFAULT '{}'::jsonb,
                options JSONB NOT NULL DEFAULT '{}'::jsonb,
                solver VARCHAR(40) NOT NULL,
                progress JSONB,
                cancel_requested BOOLEAN NOT NULL DEFAULT FALSE,
                input_fingerprint VARCHAR(64),
                input_snapshot JSONB,
                result JSONB,
                quality JSONB,
                hard_violations INTEGER,
                soft_penalty BIGINT,
                activities_total INTEGER,
                placed INTEGER,
                unplaced INTEGER,
                error TEXT,
                requested_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                applied_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                started_at TIMESTAMPTZ,
                finished_at TIMESTAMPTZ,
                applied_at TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
        DB::statement('CREATE UNIQUE INDEX generation_runs_one_active_uidx ON timetable.generation_runs (school_id, academic_year_id) WHERE status IN (1, 2)');
        DB::statement('CREATE INDEX generation_runs_school_year_idx ON timetable.generation_runs (school_id, academic_year_id, created_at DESC)');

        DB::statement('
            CREATE TABLE timetable.versions (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                academic_year_id BIGINT NOT NULL REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                version_no INTEGER NOT NULL CHECK (version_no >= 1),
                parent_version_id BIGINT REFERENCES timetable.versions(id) ON DELETE RESTRICT,
                name VARCHAR(150) NOT NULL,
                reason TEXT,
                status SMALLINT NOT NULL DEFAULT 1 CHECK (status BETWEEN 1 AND 7),
                source_fingerprint VARCHAR(64) NOT NULL,
                entries_count INTEGER NOT NULL DEFAULT 0,
                quality JSONB,
                generation_run_id BIGINT REFERENCES timetable.generation_runs(id) ON DELETE RESTRICT,
                approval_request_id BIGINT REFERENCES workflow.approval_requests(id) ON DELETE RESTRICT,
                created_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                decided_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                decided_at TIMESTAMPTZ,
                published_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                published_at TIMESTAMPTZ,
                effective_from DATE,
                effective_to DATE,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT versions_number_unique UNIQUE (school_id, academic_year_id, version_no),
                CONSTRAINT versions_id_school_unique UNIQUE (id, school_id),
                CONSTRAINT versions_effective_check CHECK (effective_to IS NULL OR (effective_from IS NOT NULL AND effective_to >= effective_from)),
                CONSTRAINT versions_published_dates CHECK (status NOT IN (5, 6) OR (published_at IS NOT NULL AND effective_from IS NOT NULL))
            )
        ');
        DB::statement('CREATE INDEX versions_school_year_idx ON timetable.versions (school_id, academic_year_id, status)');

        DB::statement('
            CREATE TABLE timetable.version_entries (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                version_id BIGINT NOT NULL,
                section_id BIGINT NOT NULL REFERENCES enrollment.sections(id) ON DELETE RESTRICT,
                group_id BIGINT REFERENCES timetable.division_groups(id) ON DELETE RESTRICT,
                day_of_week SMALLINT NOT NULL CHECK (day_of_week BETWEEN 1 AND 7),
                period_id SMALLINT NOT NULL,
                week_no SMALLINT CHECK (week_no IS NULL OR week_no BETWEEN 1 AND 4),
                subject_id BIGINT NOT NULL REFERENCES curriculum.subjects(id) ON DELETE RESTRICT,
                teacher_id BIGINT NOT NULL REFERENCES teachers.teachers(id) ON DELETE RESTRICT,
                co_teacher_id BIGINT REFERENCES teachers.teachers(id) ON DELETE RESTRICT,
                room_id BIGINT REFERENCES organization.rooms(id) ON DELETE RESTRICT,
                activity_id BIGINT REFERENCES timetable.activities(id) ON DELETE RESTRICT,
                source_schedule_id BIGINT REFERENCES timetable.schedules(id) ON DELETE RESTRICT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT version_entries_school_fk FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT version_entries_version_school_fk FOREIGN KEY (version_id, school_id)
                    REFERENCES timetable.versions(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT version_entries_period_school_fk FOREIGN KEY (period_id, school_id)
                    REFERENCES timetable.periods(id, school_id) ON DELETE RESTRICT
            )
        ');
        DB::statement('CREATE INDEX version_entries_version_section_idx ON timetable.version_entries (version_id, section_id)');
        DB::statement('CREATE INDEX version_entries_version_teacher_idx ON timetable.version_entries (version_id, teacher_id)');

        foreach (['generation_runs', 'versions', 'version_entries'] as $table) {
            DB::statement("ALTER TABLE timetable.{$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE timetable.{$table} FORCE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY {$table}_school_isolation ON timetable.{$table}
                USING (
                    NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                    AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
                WITH CHECK (
                    NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                    AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            ");
            DB::statement("
                CREATE TRIGGER {$table}_reject_delete
                BEFORE DELETE ON timetable.{$table}
                FOR EACH ROW EXECUTE FUNCTION timetable.reject_engine_delete()
            ");
        }

        DB::statement("
            CREATE OR REPLACE FUNCTION timetable.reject_version_entry_update()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'timetable.version_entries are immutable — publish a new version'
                    USING ERRCODE = 'check_violation';
            END; $$
        ");
        DB::statement('
            CREATE TRIGGER version_entries_reject_update
            BEFORE UPDATE ON timetable.version_entries
            FOR EACH ROW EXECUTE FUNCTION timetable.reject_version_entry_update()
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS timetable.version_entries');
        DB::statement('DROP FUNCTION IF EXISTS timetable.reject_version_entry_update()');
        DB::statement('DROP TABLE IF EXISTS timetable.versions');
        DB::statement('DROP TABLE IF EXISTS timetable.generation_runs');
    }
};
