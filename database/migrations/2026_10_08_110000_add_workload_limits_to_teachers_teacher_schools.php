<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Per-teacher lesson limits for the timetable, on the school/year membership (next to نوع التعيين):
 * a teacher's quota depends on the school and year and on the appointment, not on the person.
 *
 * NULL = no personal limit (the school-wide daily limit of the timetable settings applies).
 * Additive and nullable — no academic history is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE teachers.teacher_schools
            ADD COLUMN weekly_lessons_min SMALLINT NULL,
            ADD COLUMN weekly_lessons_max SMALLINT NULL,
            ADD COLUMN daily_lessons_max SMALLINT NULL,
            ADD CONSTRAINT teacher_schools_weekly_max_chk CHECK (weekly_lessons_max IS NULL OR weekly_lessons_max BETWEEN 1 AND 60),
            ADD CONSTRAINT teacher_schools_weekly_min_chk CHECK (weekly_lessons_min IS NULL OR weekly_lessons_min BETWEEN 0 AND 60),
            ADD CONSTRAINT teacher_schools_weekly_range_chk CHECK (weekly_lessons_min IS NULL OR weekly_lessons_max IS NULL OR weekly_lessons_min <= weekly_lessons_max),
            ADD CONSTRAINT teacher_schools_daily_max_chk CHECK (daily_lessons_max IS NULL OR daily_lessons_max BETWEEN 1 AND 12)');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE teachers.teacher_schools
            DROP CONSTRAINT IF EXISTS teacher_schools_weekly_max_chk,
            DROP CONSTRAINT IF EXISTS teacher_schools_weekly_min_chk,
            DROP CONSTRAINT IF EXISTS teacher_schools_weekly_range_chk,
            DROP CONSTRAINT IF EXISTS teacher_schools_daily_max_chk,
            DROP COLUMN IF EXISTS weekly_lessons_min,
            DROP COLUMN IF EXISTS weekly_lessons_max,
            DROP COLUMN IF EXISTS daily_lessons_max');
    }
};
