<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Timetable engine — the working grid learns groups, week cycles, joined classes, co-teachers and locks.
 *
 * Additive columns on timetable.schedules (all nullable, existing rows unchanged):
 *   activity_id            the activity the lesson realises (NULL = placed by hand from a requirement)
 *   group_id               a group of the section (NULL = whole section)
 *   week_no                1..4 in a multi-week cycle (NULL = every week)
 *   co_teacher_id          second teacher of a co-taught lesson
 *   joined_to_schedule_id  joined classes: the other sections' rows point at the lead row, which holds the
 *                          teacher / room occupancy (so the teacher is "busy once")
 *   locked_at / locked_by  the generator never moves a locked lesson
 *
 * The three active-slot partial uniques are re-keyed (same names) so that:
 *   section — one row per section · group · week · slot (groups of one division may share a slot;
 *             division compatibility and week overlap are enforced in the Domain);
 *   teacher / room — counted on lead rows only (joined rows excluded), per week;
 *   co-teacher — new, same rule.
 * For existing data (group, week, joined all NULL) the keys are equivalent to the old ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            ALTER TABLE timetable.schedules
                ADD COLUMN activity_id BIGINT REFERENCES timetable.activities(id) ON DELETE RESTRICT,
                ADD COLUMN group_id BIGINT REFERENCES timetable.division_groups(id) ON DELETE RESTRICT,
                ADD COLUMN week_no SMALLINT,
                ADD COLUMN co_teacher_id BIGINT REFERENCES teachers.teachers(id) ON DELETE RESTRICT,
                ADD COLUMN joined_to_schedule_id BIGINT REFERENCES timetable.schedules(id) ON DELETE RESTRICT,
                ADD COLUMN locked_at TIMESTAMPTZ,
                ADD COLUMN locked_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL
        ');
        DB::statement('ALTER TABLE timetable.schedules ADD CONSTRAINT schedules_week_no_check CHECK (week_no IS NULL OR week_no BETWEEN 1 AND 4)');
        DB::statement('ALTER TABLE timetable.schedules ADD CONSTRAINT schedules_co_teacher_check CHECK (co_teacher_id IS NULL OR co_teacher_id <> teacher_id)');
        DB::statement('ALTER TABLE timetable.schedules ADD CONSTRAINT schedules_joined_not_self CHECK (joined_to_schedule_id IS NULL OR joined_to_schedule_id <> id)');

        DB::statement('DROP INDEX IF EXISTS timetable.schedules_section_slot_active_uidx');
        DB::statement('
            CREATE UNIQUE INDEX schedules_section_slot_active_uidx ON timetable.schedules
            (section_id, academic_year_id, day_of_week, period_id, COALESCE(group_id, 0), COALESCE(week_no, 0))
            WHERE cancelled_at IS NULL
        ');
        DB::statement('DROP INDEX IF EXISTS timetable.schedules_teacher_slot_active_uidx');
        DB::statement('
            CREATE UNIQUE INDEX schedules_teacher_slot_active_uidx ON timetable.schedules
            (teacher_id, academic_year_id, day_of_week, period_id, COALESCE(week_no, 0))
            WHERE cancelled_at IS NULL AND joined_to_schedule_id IS NULL
        ');
        DB::statement('DROP INDEX IF EXISTS timetable.schedules_room_slot_active_uidx');
        DB::statement('
            CREATE UNIQUE INDEX schedules_room_slot_active_uidx ON timetable.schedules
            (room_id, academic_year_id, day_of_week, period_id, COALESCE(week_no, 0))
            WHERE cancelled_at IS NULL AND room_id IS NOT NULL AND joined_to_schedule_id IS NULL
        ');
        DB::statement('
            CREATE UNIQUE INDEX schedules_co_teacher_slot_active_uidx ON timetable.schedules
            (co_teacher_id, academic_year_id, day_of_week, period_id, COALESCE(week_no, 0))
            WHERE cancelled_at IS NULL AND co_teacher_id IS NOT NULL AND joined_to_schedule_id IS NULL
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS timetable.schedules_co_teacher_slot_active_uidx');
        DB::statement('DROP INDEX IF EXISTS timetable.schedules_section_slot_active_uidx');
        DB::statement('DROP INDEX IF EXISTS timetable.schedules_teacher_slot_active_uidx');
        DB::statement('DROP INDEX IF EXISTS timetable.schedules_room_slot_active_uidx');
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
        DB::statement('ALTER TABLE timetable.schedules DROP CONSTRAINT IF EXISTS schedules_joined_not_self');
        DB::statement('ALTER TABLE timetable.schedules DROP CONSTRAINT IF EXISTS schedules_co_teacher_check');
        DB::statement('ALTER TABLE timetable.schedules DROP CONSTRAINT IF EXISTS schedules_week_no_check');
        DB::statement('
            ALTER TABLE timetable.schedules
                DROP COLUMN IF EXISTS locked_by,
                DROP COLUMN IF EXISTS locked_at,
                DROP COLUMN IF EXISTS joined_to_schedule_id,
                DROP COLUMN IF EXISTS co_teacher_id,
                DROP COLUMN IF EXISTS week_no,
                DROP COLUMN IF EXISTS group_id,
                DROP COLUMN IF EXISTS activity_id
        ');
    }
};
