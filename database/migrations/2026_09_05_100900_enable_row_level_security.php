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

        DB::statement('ALTER TABLE enrollment.enrollments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE attendance.records ENABLE ROW LEVEL SECURITY');

        DB::statement("
            CREATE POLICY enrollment_school_isolation ON enrollment.enrollments
            USING (
                school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                OR current_setting('app.current_school_id', true) IS NULL
                OR current_setting('app.current_school_id', true) = ''
            )
        ");

        DB::statement("
            CREATE POLICY attendance_school_isolation ON attendance.records
            USING (
                school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                OR current_setting('app.current_school_id', true) IS NULL
                OR current_setting('app.current_school_id', true) = ''
            )
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS attendance_school_isolation ON attendance.records');
        DB::statement('DROP POLICY IF EXISTS enrollment_school_isolation ON enrollment.enrollments');
        DB::statement('ALTER TABLE attendance.records DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE enrollment.enrollments DISABLE ROW LEVEL SECURITY');
    }
};
