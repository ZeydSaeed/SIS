<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 8.6-U01 — soft leave on teacher_schools via left_at + active-only body RLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            ALTER TABLE teachers.teacher_schools
            ADD COLUMN IF NOT EXISTS left_at TIMESTAMPTZ
        ');

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
                      AND ts.left_at IS NULL
                )
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
            )
        ");

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
                      AND ts.left_at IS NULL
                )
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1
                    FROM teachers.teacher_schools ts
                    WHERE ts.teacher_id = teacher_qualifications.teacher_id
                      AND ts.school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                      AND ts.left_at IS NULL
                )
            )
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

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

        DB::statement('ALTER TABLE teachers.teacher_schools DROP COLUMN IF EXISTS left_at');
    }
};
