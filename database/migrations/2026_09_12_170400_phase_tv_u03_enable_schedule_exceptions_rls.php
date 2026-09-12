<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE timetable.schedule_exceptions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE timetable.schedule_exceptions FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY schedule_exceptions_school_isolation ON timetable.schedule_exceptions
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS schedule_exceptions_school_isolation ON timetable.schedule_exceptions');
        DB::statement('ALTER TABLE timetable.schedule_exceptions NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE timetable.schedule_exceptions DISABLE ROW LEVEL SECURITY');
    }
};
