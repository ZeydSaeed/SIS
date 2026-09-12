<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 8-U01 — FORCE RLS on teacher_schools / teacher_subjects;
 * reject hard DELETE on teachers.* (4 tables).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE teachers.teacher_schools ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE teachers.teacher_schools FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS teacher_schools_school_isolation ON teachers.teacher_schools');
        DB::statement("
            CREATE POLICY teacher_schools_school_isolation ON teachers.teacher_schools
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");

        DB::statement('ALTER TABLE teachers.teacher_subjects ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE teachers.teacher_subjects FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS teacher_subjects_school_isolation ON teachers.teacher_subjects');
        DB::statement("
            CREATE POLICY teacher_subjects_school_isolation ON teachers.teacher_subjects
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");

        foreach (['teachers', 'teacher_schools', 'teacher_subjects', 'teacher_qualifications'] as $table) {
            DB::statement("
                CREATE OR REPLACE FUNCTION teachers.reject_{$table}_delete()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $$
                BEGIN
                    RAISE EXCEPTION 'Hard delete of teachers.{$table} is forbidden';
                END;
                $$
            ");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_reject_delete ON teachers.{$table}");
            DB::statement("
                CREATE TRIGGER {$table}_reject_delete
                BEFORE DELETE ON teachers.{$table}
                FOR EACH ROW
                EXECUTE FUNCTION teachers.reject_{$table}_delete()
            ");
        }
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        foreach (['teachers', 'teacher_schools', 'teacher_subjects', 'teacher_qualifications'] as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_reject_delete ON teachers.{$table}");
            DB::statement("DROP FUNCTION IF EXISTS teachers.reject_{$table}_delete()");
        }

        DB::statement('DROP POLICY IF EXISTS teacher_subjects_school_isolation ON teachers.teacher_subjects');
        DB::statement('ALTER TABLE teachers.teacher_subjects NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE teachers.teacher_subjects DISABLE ROW LEVEL SECURITY');

        DB::statement('DROP POLICY IF EXISTS teacher_schools_school_isolation ON teachers.teacher_schools');
        DB::statement('ALTER TABLE teachers.teacher_schools NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE teachers.teacher_schools DISABLE ROW LEVEL SECURITY');
    }
};
