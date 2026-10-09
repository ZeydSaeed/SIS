<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Timetable workbench — the school day and the presentation of the timetable.
 *
 * timetable.periods (additive; SMALLINT PK and the attendance contract untouched):
 *   name, abbreviation, color_hue          «استراحة الطلاب بعد الحصة الرابعة», short label, tint
 *   show_in / print_in SMALLINT bitmask   where the period appears / prints: 1 general · 2 teachers · 4 sections ·
 *                                          8 students · 16 rooms (default 31 = everywhere)
 *   status 1 active · 2 retired           a removed break is retired, never deleted (reject-delete trigger stays);
 *                                          the period-number UNIQUE becomes partial on active rows so a retired
 *                                          number can be reused.
 *
 * timetable.configs.display JSONB          cell layout, visible fields, fonts, alignment, sizes (per school-year)
 *
 * timetable.test_marks                     «اختبار الجدول»: an issue the user chose to ignore or to review later
 *                                          (keyed by the issue's stable key); cleared by status, never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            ALTER TABLE timetable.periods
                ADD COLUMN name VARCHAR(60),
                ADD COLUMN abbreviation VARCHAR(20),
                ADD COLUMN color_hue SMALLINT CHECK (color_hue IS NULL OR color_hue BETWEEN 0 AND 359),
                ADD COLUMN show_in SMALLINT NOT NULL DEFAULT 31 CHECK (show_in BETWEEN 0 AND 31),
                ADD COLUMN print_in SMALLINT NOT NULL DEFAULT 31 CHECK (print_in BETWEEN 0 AND 31),
                ADD COLUMN status SMALLINT NOT NULL DEFAULT 1 CHECK (status IN (1, 2))
        ');
        DB::statement('ALTER TABLE timetable.periods DROP CONSTRAINT IF EXISTS timetable_periods_school_id_period_number_unique');
        DB::statement('CREATE UNIQUE INDEX periods_school_number_active_uidx ON timetable.periods (school_id, period_number) WHERE status = 1');

        DB::statement('ALTER TABLE timetable.configs ADD COLUMN display JSONB');
        DB::statement("ALTER TABLE timetable.configs ADD CONSTRAINT configs_display_object CHECK (display IS NULL OR jsonb_typeof(display) = 'object')");

        DB::statement('
            CREATE TABLE timetable.test_marks (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                academic_year_id BIGINT NOT NULL REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                issue_key VARCHAR(200) NOT NULL,
                mark SMALLINT NOT NULL CHECK (mark IN (1, 2)),
                note VARCHAR(255),
                status SMALLINT NOT NULL DEFAULT 1 CHECK (status IN (1, 2)),
                marked_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ');
        DB::statement('CREATE UNIQUE INDEX test_marks_active_uidx ON timetable.test_marks (school_id, academic_year_id, issue_key) WHERE status = 1');
        DB::statement('ALTER TABLE timetable.test_marks ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE timetable.test_marks FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY test_marks_school_isolation ON timetable.test_marks
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");
        DB::statement('CREATE TRIGGER test_marks_reject_delete BEFORE DELETE ON timetable.test_marks FOR EACH ROW EXECUTE FUNCTION timetable.reject_engine_delete()');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS test_marks_reject_delete ON timetable.test_marks');
        DB::statement('DROP TABLE IF EXISTS timetable.test_marks');

        DB::statement('ALTER TABLE timetable.configs DROP CONSTRAINT IF EXISTS configs_display_object');
        DB::statement('ALTER TABLE timetable.configs DROP COLUMN IF EXISTS display');

        // Retired periods keep their rows; restoring the full UNIQUE needs distinct numbers, so park retired ones
        // above the active range (period numbers are 1–20 for active periods).
        DB::statement('UPDATE timetable.periods SET period_number = 100 + id WHERE status = 2');
        DB::statement('DROP INDEX IF EXISTS timetable.periods_school_number_active_uidx');
        DB::statement('ALTER TABLE timetable.periods ADD CONSTRAINT timetable_periods_school_id_period_number_unique UNIQUE (school_id, period_number)');
        DB::statement('
            ALTER TABLE timetable.periods
                DROP COLUMN IF EXISTS name,
                DROP COLUMN IF EXISTS abbreviation,
                DROP COLUMN IF EXISTS color_hue,
                DROP COLUMN IF EXISTS show_in,
                DROP COLUMN IF EXISTS print_in,
                DROP COLUMN IF EXISTS status
        ');
    }
};
