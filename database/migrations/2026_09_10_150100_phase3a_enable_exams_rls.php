<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3A — fail-closed RLS + FORCE on school-scoped exam tables.
 * exam_types is global reference — no RLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE exams.exams ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE exams.exam_sessions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE exams.exam_enrollments ENABLE ROW LEVEL SECURITY');

        DB::statement('ALTER TABLE exams.exams FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE exams.exam_sessions FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE exams.exam_enrollments FORCE ROW LEVEL SECURITY');

        DB::statement("
            CREATE POLICY exams_school_isolation ON exams.exams
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");

        DB::statement("
            CREATE POLICY exam_sessions_school_isolation ON exams.exam_sessions
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");

        DB::statement("
            CREATE POLICY exam_enrollments_school_isolation ON exams.exam_enrollments
            USING (
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

        DB::statement('DROP POLICY IF EXISTS exam_enrollments_school_isolation ON exams.exam_enrollments');
        DB::statement('DROP POLICY IF EXISTS exam_sessions_school_isolation ON exams.exam_sessions');
        DB::statement('DROP POLICY IF EXISTS exams_school_isolation ON exams.exams');

        DB::statement('ALTER TABLE exams.exam_enrollments NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE exams.exam_sessions NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE exams.exams NO FORCE ROW LEVEL SECURITY');

        DB::statement('ALTER TABLE exams.exam_enrollments DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE exams.exam_sessions DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE exams.exams DISABLE ROW LEVEL SECURITY');
    }
};
