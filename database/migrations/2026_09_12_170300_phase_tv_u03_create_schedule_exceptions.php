<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase TV-U03 — physicalize timetable.schedule_exceptions (date substitutes).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS schedules_id_school_id_unique
            ON timetable.schedules (id, school_id)
        ');

        DB::statement("
            CREATE TABLE timetable.schedule_exceptions (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                schedule_id BIGINT NOT NULL,
                exception_date DATE NOT NULL,
                substitute_teacher_id BIGINT,
                substitute_room_id BIGINT,
                reason TEXT,
                correlation_id VARCHAR(64),
                created_by BIGINT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT schedule_exceptions_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT schedule_exceptions_schedule_school_fk
                    FOREIGN KEY (schedule_id, school_id)
                    REFERENCES timetable.schedules(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT schedule_exceptions_sub_teacher_fk
                    FOREIGN KEY (substitute_teacher_id) REFERENCES teachers.teachers(id) ON DELETE RESTRICT,
                CONSTRAINT schedule_exceptions_sub_room_fk
                    FOREIGN KEY (substitute_room_id) REFERENCES organization.rooms(id) ON DELETE RESTRICT,
                CONSTRAINT schedule_exceptions_created_by_fk
                    FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL
            )
        ");

        DB::statement('
            CREATE UNIQUE INDEX schedule_exceptions_schedule_date_uidx
            ON timetable.schedule_exceptions (schedule_id, exception_date)
        ');
        DB::statement('
            CREATE INDEX schedule_exceptions_date_idx
            ON timetable.schedule_exceptions (exception_date)
        ');
        DB::statement('
            CREATE INDEX schedule_exceptions_school_idx
            ON timetable.schedule_exceptions (school_id)
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION timetable.reject_schedule_exceptions_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of timetable.schedule_exceptions is forbidden';
            END; $$
        ");
        DB::statement('
            CREATE TRIGGER schedule_exceptions_reject_delete
            BEFORE DELETE ON timetable.schedule_exceptions
            FOR EACH ROW EXECUTE FUNCTION timetable.reject_schedule_exceptions_delete()
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS schedule_exceptions_reject_delete ON timetable.schedule_exceptions');
        DB::statement('DROP FUNCTION IF EXISTS timetable.reject_schedule_exceptions_delete()');
        DB::statement('DROP TABLE IF EXISTS timetable.schedule_exceptions');
    }
};
