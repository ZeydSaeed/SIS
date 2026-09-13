<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase CUR-U05 — harden enrollment.enrollment_subjects (FORCE RLS + soft-only).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            ALTER TABLE enrollment.enrollment_subjects
            DROP CONSTRAINT IF EXISTS enrollment_enrollment_subjects_status_check
        ');
        DB::statement('
            ALTER TABLE enrollment.enrollment_subjects
            ADD CONSTRAINT enrollment_enrollment_subjects_status_check
            CHECK (status IN (1, 2))
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION enrollment.reject_enrollment_subjects_hard_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard DELETE of enrollment.enrollment_subjects is forbidden — soft-deactivate via status'
                    USING ERRCODE = 'check_violation';
            END; $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS enrollment_enrollment_subjects_reject_hard_delete ON enrollment.enrollment_subjects');
        DB::statement("
            CREATE TRIGGER enrollment_enrollment_subjects_reject_hard_delete
            BEFORE DELETE ON enrollment.enrollment_subjects
            FOR EACH ROW
            EXECUTE FUNCTION enrollment.reject_enrollment_subjects_hard_delete()
        ");

        DB::statement('ALTER TABLE enrollment.enrollment_subjects ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE enrollment.enrollment_subjects FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS enrollment_subjects_school_isolation ON enrollment.enrollment_subjects');
        DB::statement("
            CREATE POLICY enrollment_subjects_school_isolation ON enrollment.enrollment_subjects
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM enrollment.enrollments e
                    WHERE e.id = enrollment_subjects.enrollment_id
                      AND e.school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM enrollment.enrollments e
                    WHERE e.id = enrollment_subjects.enrollment_id
                      AND e.school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            )
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS enrollment_subjects_school_isolation ON enrollment.enrollment_subjects');
        DB::statement('ALTER TABLE enrollment.enrollment_subjects NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE enrollment.enrollment_subjects DISABLE ROW LEVEL SECURITY');
        DB::statement('DROP TRIGGER IF EXISTS enrollment_enrollment_subjects_reject_hard_delete ON enrollment.enrollment_subjects');
        DB::statement('DROP FUNCTION IF EXISTS enrollment.reject_enrollment_subjects_hard_delete()');
        DB::statement('ALTER TABLE enrollment.enrollment_subjects DROP CONSTRAINT IF EXISTS enrollment_enrollment_subjects_status_check');
    }
};
