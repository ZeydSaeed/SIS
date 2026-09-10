<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3B — fail-closed RLS + FORCE on exams.student_grades.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE exams.student_grades ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE exams.student_grades FORCE ROW LEVEL SECURITY');

        DB::statement("
            CREATE POLICY student_grades_school_isolation ON exams.student_grades
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

        DB::statement('DROP POLICY IF EXISTS student_grades_school_isolation ON exams.student_grades');
        DB::statement('ALTER TABLE exams.student_grades NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE exams.student_grades DISABLE ROW LEVEL SECURITY');
    }
};
