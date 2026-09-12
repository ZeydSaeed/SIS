<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase TV-U02 — physicalize timetable.schedules (school_id, soft cancel, conflict uniques).
 * Blueprint object already counted — no object-count inflation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS periods_id_school_id_unique
            ON timetable.periods (id, school_id)
        ');

        DB::statement("
            CREATE TABLE timetable.schedules (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                section_id BIGINT NOT NULL,
                academic_year_id BIGINT NOT NULL,
                day_of_week SMALLINT NOT NULL,
                period_id SMALLINT NOT NULL,
                subject_id BIGINT NOT NULL,
                teacher_id BIGINT NOT NULL,
                room_id BIGINT,
                lifecycle_status SMALLINT NOT NULL DEFAULT 1,
                cancelled_at TIMESTAMPTZ,
                correlation_id VARCHAR(64),
                created_by BIGINT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT schedules_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT schedules_section_fk
                    FOREIGN KEY (section_id) REFERENCES enrollment.sections(id) ON DELETE RESTRICT,
                CONSTRAINT schedules_year_fk
                    FOREIGN KEY (academic_year_id) REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                CONSTRAINT schedules_period_school_fk
                    FOREIGN KEY (period_id, school_id)
                    REFERENCES timetable.periods(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT schedules_subject_fk
                    FOREIGN KEY (subject_id) REFERENCES curriculum.subjects(id) ON DELETE RESTRICT,
                CONSTRAINT schedules_teacher_fk
                    FOREIGN KEY (teacher_id) REFERENCES teachers.teachers(id) ON DELETE RESTRICT,
                CONSTRAINT schedules_room_fk
                    FOREIGN KEY (room_id) REFERENCES organization.rooms(id) ON DELETE RESTRICT,
                CONSTRAINT schedules_created_by_fk
                    FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL
            )
        ");

        DB::statement('
            ALTER TABLE timetable.schedules
            ADD CONSTRAINT schedules_day_of_week_check
            CHECK (day_of_week BETWEEN 1 AND 7)
        ');
        DB::statement('
            ALTER TABLE timetable.schedules
            ADD CONSTRAINT schedules_lifecycle_check
            CHECK (lifecycle_status BETWEEN 1 AND 2)
        ');
        DB::statement('
            ALTER TABLE timetable.schedules
            ADD CONSTRAINT schedules_cancelled_consistency_check
            CHECK (
                (lifecycle_status = 1 AND cancelled_at IS NULL)
                OR (lifecycle_status = 2 AND cancelled_at IS NOT NULL)
            )
        ');

        DB::statement('
            CREATE UNIQUE INDEX schedules_section_slot_active_uidx
            ON timetable.schedules (section_id, academic_year_id, day_of_week, period_id)
            WHERE cancelled_at IS NULL
        ');
        DB::statement('
            CREATE UNIQUE INDEX schedules_teacher_slot_active_uidx
            ON timetable.schedules (teacher_id, academic_year_id, day_of_week, period_id)
            WHERE cancelled_at IS NULL
        ');
        DB::statement('
            CREATE UNIQUE INDEX schedules_room_slot_active_uidx
            ON timetable.schedules (room_id, academic_year_id, day_of_week, period_id)
            WHERE cancelled_at IS NULL AND room_id IS NOT NULL
        ');
        DB::statement('
            CREATE INDEX schedules_school_year_idx
            ON timetable.schedules (school_id, academic_year_id)
        ');
        DB::statement('
            CREATE INDEX schedules_section_year_idx
            ON timetable.schedules (section_id, academic_year_id)
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION timetable.reject_schedules_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of timetable.schedules is forbidden';
            END; $$
        ");
        DB::statement('
            CREATE TRIGGER schedules_reject_delete
            BEFORE DELETE ON timetable.schedules
            FOR EACH ROW EXECUTE FUNCTION timetable.reject_schedules_delete()
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS schedules_reject_delete ON timetable.schedules');
        DB::statement('DROP FUNCTION IF EXISTS timetable.reject_schedules_delete()');
        DB::statement('DROP TABLE IF EXISTS timetable.schedules');
    }
};
