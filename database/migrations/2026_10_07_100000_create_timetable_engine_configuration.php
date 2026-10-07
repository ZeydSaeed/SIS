<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Timetable engine — configuration side (docs/timetable/TIMETABLE-DOMAIN-SPECIFICATION.md §6, decision D1).
 *
 * - configs           working days, cycle weeks, daily limits, double changeover, soft weights (per school-year)
 * - divisions         a section split into groups; groups of ONE division may be taught at the same time
 * - division_groups   the groups (name, student count)
 * - group_members     which enrollment sits in which group (student-level timetable)
 * - activities        the schedulable unit (subject, type, weekly count, block length, distribution, room needs, week pattern)
 * - activity_sections targets — n sections (joined classes), optionally one group each (divided lessons)
 * - activity_teachers lead / co-teacher / assistant; `sessions` = occurrences the teacher attends (NULL = all)
 * - availability      unavailable / avoid / preferred slots for exactly one teacher, room, section or workshop
 * - constraint_rules  typed, prioritised, explicitly scoped rules (no entity_type/entity_id), params JSONB
 *
 * Every table: school_id NOT NULL, FORCE RLS on app.current_school_id, hard DELETE rejected (rows end by status).
 * Reuses SIS facts by FK only — no copy of sections, teachers, subjects, rooms, workshops or terms.
 */
return new class extends Migration
{
    private const TABLES = [
        'configs', 'divisions', 'division_groups', 'group_members', 'activities',
        'activity_sections', 'activity_teachers', 'availability', 'constraint_rules',
    ];

    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement("
            CREATE OR REPLACE FUNCTION timetable.reject_engine_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of timetable.% is forbidden — end the row by status', TG_TABLE_NAME
                    USING ERRCODE = 'check_violation';
            END; $$
        ");

        DB::statement("
            CREATE TABLE timetable.configs (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                academic_year_id BIGINT NOT NULL REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                working_days JSONB NOT NULL DEFAULT '[1,2,3,4,5]'::jsonb,
                cycle_weeks SMALLINT NOT NULL DEFAULT 1 CHECK (cycle_weeks BETWEEN 1 AND 4),
                max_teacher_per_day SMALLINT NOT NULL DEFAULT 6 CHECK (max_teacher_per_day BETWEEN 1 AND 20),
                max_subject_per_day SMALLINT NOT NULL DEFAULT 2 CHECK (max_subject_per_day BETWEEN 1 AND 20),
                double_changeover_minutes SMALLINT NOT NULL DEFAULT 10 CHECK (double_changeover_minutes BETWEEN 0 AND 60),
                weights JSONB,
                updated_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT configs_school_year_unique UNIQUE (school_id, academic_year_id),
                CONSTRAINT configs_working_days_array CHECK (jsonb_typeof(working_days) = 'array' AND jsonb_array_length(working_days) BETWEEN 1 AND 7)
            )
        ");

        DB::statement('
            CREATE TABLE timetable.divisions (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                academic_year_id BIGINT NOT NULL REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                section_id BIGINT NOT NULL REFERENCES enrollment.sections(id) ON DELETE RESTRICT,
                name VARCHAR(100) NOT NULL,
                status SMALLINT NOT NULL DEFAULT 1 CHECK (status IN (1, 2)),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ');
        DB::statement('CREATE INDEX divisions_school_year_idx ON timetable.divisions (school_id, academic_year_id)');
        DB::statement('CREATE INDEX divisions_section_idx ON timetable.divisions (section_id)');

        DB::statement('
            CREATE TABLE timetable.division_groups (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                division_id BIGINT NOT NULL REFERENCES timetable.divisions(id) ON DELETE RESTRICT,
                name VARCHAR(100) NOT NULL,
                student_count SMALLINT CHECK (student_count IS NULL OR student_count >= 0),
                status SMALLINT NOT NULL DEFAULT 1 CHECK (status IN (1, 2)),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ');
        DB::statement('CREATE INDEX division_groups_division_idx ON timetable.division_groups (division_id)');

        DB::statement('
            CREATE TABLE timetable.group_members (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                group_id BIGINT NOT NULL REFERENCES timetable.division_groups(id) ON DELETE RESTRICT,
                enrollment_id BIGINT NOT NULL REFERENCES enrollment.enrollments(id) ON DELETE RESTRICT,
                status SMALLINT NOT NULL DEFAULT 1 CHECK (status IN (1, 2)),
                effective_from DATE NOT NULL DEFAULT CURRENT_DATE,
                effective_to DATE,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT group_members_effective_check CHECK (effective_to IS NULL OR effective_to >= effective_from)
            )
        ');
        DB::statement('CREATE UNIQUE INDEX group_members_active_uidx ON timetable.group_members (group_id, enrollment_id) WHERE status = 1');
        DB::statement('CREATE INDEX group_members_enrollment_idx ON timetable.group_members (enrollment_id)');

        DB::statement("
            CREATE TABLE timetable.activities (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                academic_year_id BIGINT NOT NULL REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                subject_id BIGINT NOT NULL REFERENCES curriculum.subjects(id) ON DELETE RESTRICT,
                activity_type SMALLINT NOT NULL DEFAULT 1 CHECK (activity_type BETWEEN 1 AND 17),
                weekly_count SMALLINT NOT NULL CHECK (weekly_count BETWEEN 1 AND 40),
                block_length SMALLINT NOT NULL DEFAULT 1 CHECK (block_length BETWEEN 1 AND 6),
                distribution VARCHAR(30),
                distribution_fixed BOOLEAN NOT NULL DEFAULT FALSE,
                room_id BIGINT REFERENCES organization.rooms(id) ON DELETE RESTRICT,
                room_type SMALLINT,
                workshop_id BIGINT REFERENCES vocational.workshops(id) ON DELETE RESTRICT,
                week_pattern SMALLINT NOT NULL DEFAULT 0 CHECK (week_pattern BETWEEN 0 AND 4),
                term_id BIGINT REFERENCES academic.terms(id) ON DELETE RESTRICT,
                note VARCHAR(255),
                status SMALLINT NOT NULL DEFAULT 1 CHECK (status IN (1, 2)),
                effective_from DATE NOT NULL DEFAULT CURRENT_DATE,
                effective_to DATE,
                created_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT activities_distribution_format CHECK (distribution IS NULL OR distribution ~ '^[1-6](\\+[1-6])*$'),
                CONSTRAINT activities_effective_check CHECK (effective_to IS NULL OR effective_to >= effective_from),
                CONSTRAINT activities_ended_check CHECK ((status = 1 AND effective_to IS NULL) OR status = 2)
            )
        ");
        DB::statement('CREATE INDEX activities_school_year_idx ON timetable.activities (school_id, academic_year_id, status)');

        DB::statement('
            CREATE TABLE timetable.activity_sections (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                activity_id BIGINT NOT NULL REFERENCES timetable.activities(id) ON DELETE RESTRICT,
                section_id BIGINT NOT NULL REFERENCES enrollment.sections(id) ON DELETE RESTRICT,
                group_id BIGINT REFERENCES timetable.division_groups(id) ON DELETE RESTRICT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ');
        DB::statement('CREATE UNIQUE INDEX activity_sections_uidx ON timetable.activity_sections (activity_id, section_id, COALESCE(group_id, 0))');
        DB::statement('CREATE INDEX activity_sections_section_idx ON timetable.activity_sections (section_id)');

        DB::statement('
            CREATE TABLE timetable.activity_teachers (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                activity_id BIGINT NOT NULL REFERENCES timetable.activities(id) ON DELETE RESTRICT,
                teacher_id BIGINT NOT NULL REFERENCES teachers.teachers(id) ON DELETE RESTRICT,
                role SMALLINT NOT NULL DEFAULT 1 CHECK (role IN (1, 2, 3)),
                sessions SMALLINT CHECK (sessions IS NULL OR sessions >= 1),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT activity_teachers_unique UNIQUE (activity_id, teacher_id)
            )
        ');
        DB::statement('CREATE UNIQUE INDEX activity_teachers_one_lead_uidx ON timetable.activity_teachers (activity_id) WHERE role = 1');
        DB::statement('CREATE INDEX activity_teachers_teacher_idx ON timetable.activity_teachers (teacher_id)');

        DB::statement('
            CREATE TABLE timetable.availability (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                academic_year_id BIGINT NOT NULL REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                teacher_id BIGINT REFERENCES teachers.teachers(id) ON DELETE RESTRICT,
                room_id BIGINT REFERENCES organization.rooms(id) ON DELETE RESTRICT,
                section_id BIGINT REFERENCES enrollment.sections(id) ON DELETE RESTRICT,
                workshop_id BIGINT REFERENCES vocational.workshops(id) ON DELETE RESTRICT,
                day_of_week SMALLINT NOT NULL CHECK (day_of_week BETWEEN 1 AND 7),
                period_id SMALLINT NOT NULL,
                week_no SMALLINT CHECK (week_no IS NULL OR week_no BETWEEN 1 AND 4),
                kind SMALLINT NOT NULL DEFAULT 1 CHECK (kind IN (1, 2, 3)),
                reason VARCHAR(255),
                status SMALLINT NOT NULL DEFAULT 1 CHECK (status IN (1, 2)),
                created_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT availability_period_school_fk FOREIGN KEY (period_id, school_id)
                    REFERENCES timetable.periods(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT availability_one_target CHECK (num_nonnulls(teacher_id, room_id, section_id, workshop_id) = 1)
            )
        ');
        DB::statement('
            CREATE UNIQUE INDEX availability_slot_active_uidx ON timetable.availability (
                school_id, academic_year_id, COALESCE(teacher_id, 0), COALESCE(room_id, 0), COALESCE(section_id, 0),
                COALESCE(workshop_id, 0), day_of_week, period_id, COALESCE(week_no, 0)
            ) WHERE status = 1
        ');
        DB::statement('CREATE INDEX availability_school_year_idx ON timetable.availability (school_id, academic_year_id, status)');

        DB::statement("
            CREATE TABLE timetable.constraint_rules (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                academic_year_id BIGINT NOT NULL REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                rule_type VARCHAR(60) NOT NULL,
                priority SMALLINT NOT NULL DEFAULT 4 CHECK (priority BETWEEN 1 AND 6),
                branch_id BIGINT REFERENCES organization.branches(id) ON DELETE RESTRICT,
                department_id BIGINT REFERENCES organization.departments(id) ON DELETE RESTRICT,
                grade_level_id SMALLINT REFERENCES academic.grade_levels(id) ON DELETE RESTRICT,
                class_id BIGINT REFERENCES enrollment.classes(id) ON DELETE RESTRICT,
                section_id BIGINT REFERENCES enrollment.sections(id) ON DELETE RESTRICT,
                teacher_id BIGINT REFERENCES teachers.teachers(id) ON DELETE RESTRICT,
                subject_id BIGINT REFERENCES curriculum.subjects(id) ON DELETE RESTRICT,
                room_id BIGINT REFERENCES organization.rooms(id) ON DELETE RESTRICT,
                activity_id BIGINT REFERENCES timetable.activities(id) ON DELETE RESTRICT,
                other_activity_id BIGINT REFERENCES timetable.activities(id) ON DELETE RESTRICT,
                params JSONB NOT NULL DEFAULT '{}'::jsonb,
                source SMALLINT NOT NULL DEFAULT 2 CHECK (source BETWEEN 1 AND 3),
                reason VARCHAR(255),
                status SMALLINT NOT NULL DEFAULT 1 CHECK (status IN (1, 2)),
                effective_from DATE NOT NULL DEFAULT CURRENT_DATE,
                effective_to DATE,
                created_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT constraint_rules_params_object CHECK (jsonb_typeof(params) = 'object'),
                CONSTRAINT constraint_rules_effective_check CHECK (effective_to IS NULL OR effective_to >= effective_from)
            )
        ");
        DB::statement('CREATE INDEX constraint_rules_school_year_idx ON timetable.constraint_rules (school_id, academic_year_id, status)');

        foreach (self::TABLES as $table) {
            $this->protect($table);
        }
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        foreach (array_reverse(self::TABLES) as $table) {
            DB::statement("DROP TABLE IF EXISTS timetable.{$table}");
        }
        DB::statement('DROP FUNCTION IF EXISTS timetable.reject_engine_delete()');
    }

    private function protect(string $table): void
    {
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
};
