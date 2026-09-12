<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 8-U04 — FORCE RLS on teachers.teachers + teacher_qualifications
 * via teacher_schools membership (no school_id on body tables).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE teachers.teachers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE teachers.teachers FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS teachers_body_school_isolation ON teachers.teachers');
        DB::statement("
            CREATE POLICY teachers_body_school_isolation ON teachers.teachers
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1
                    FROM teachers.teacher_schools ts
                    WHERE ts.teacher_id = teachers.id
                      AND ts.school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
            )
        ");

        DB::statement('ALTER TABLE teachers.teacher_qualifications ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE teachers.teacher_qualifications FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS teacher_qualifications_school_isolation ON teachers.teacher_qualifications');
        DB::statement("
            CREATE POLICY teacher_qualifications_school_isolation ON teachers.teacher_qualifications
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1
                    FROM teachers.teacher_schools ts
                    WHERE ts.teacher_id = teacher_qualifications.teacher_id
                      AND ts.school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1
                    FROM teachers.teacher_schools ts
                    WHERE ts.teacher_id = teacher_qualifications.teacher_id
                      AND ts.school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            )
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS teacher_qualifications_school_isolation ON teachers.teacher_qualifications');
        DB::statement('ALTER TABLE teachers.teacher_qualifications NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE teachers.teacher_qualifications DISABLE ROW LEVEL SECURITY');

        DB::statement('DROP POLICY IF EXISTS teachers_body_school_isolation ON teachers.teachers');
        DB::statement('ALTER TABLE teachers.teachers NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE teachers.teachers DISABLE ROW LEVEL SECURITY');
    }
};
