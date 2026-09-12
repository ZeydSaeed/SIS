<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase TV-U01 — harden live timetable.periods: FORCE RLS + reject hard DELETE.
 * Does not alter PK (SMALLINT) or attendance.sessions.period_id contract.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE timetable.periods ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE timetable.periods FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS periods_school_isolation ON timetable.periods');
        DB::statement("
            CREATE POLICY periods_school_isolation ON timetable.periods
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
            CREATE OR REPLACE FUNCTION timetable.reject_periods_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of timetable.periods is forbidden';
            END; $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS periods_reject_delete ON timetable.periods');
        DB::statement('
            CREATE TRIGGER periods_reject_delete
            BEFORE DELETE ON timetable.periods
            FOR EACH ROW EXECUTE FUNCTION timetable.reject_periods_delete()
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS periods_reject_delete ON timetable.periods');
        DB::statement('DROP FUNCTION IF EXISTS timetable.reject_periods_delete()');
        DB::statement('DROP POLICY IF EXISTS periods_school_isolation ON timetable.periods');
        DB::statement('ALTER TABLE timetable.periods NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE timetable.periods DISABLE ROW LEVEL SECURITY');
    }
};
