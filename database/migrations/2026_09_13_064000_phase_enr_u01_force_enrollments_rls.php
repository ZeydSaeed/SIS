<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase ENR-U01 — FORCE RLS + fail-closed policy + reject hard DELETE on enrollment.enrollments.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE enrollment.enrollments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE enrollment.enrollments FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS enrollment_school_isolation ON enrollment.enrollments');
        DB::statement("
            CREATE POLICY enrollment_school_isolation ON enrollment.enrollments
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
            CREATE OR REPLACE FUNCTION enrollment.reject_enrollments_hard_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Hard DELETE of enrollment.enrollments is forbidden — cancel via status'
                    USING ERRCODE = 'check_violation';
            END;
            $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS enrollment_enrollments_reject_hard_delete ON enrollment.enrollments');
        DB::statement("
            CREATE TRIGGER enrollment_enrollments_reject_hard_delete
            BEFORE DELETE ON enrollment.enrollments
            FOR EACH ROW
            EXECUTE FUNCTION enrollment.reject_enrollments_hard_delete()
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS enrollment_enrollments_reject_hard_delete ON enrollment.enrollments');
        DB::statement('DROP FUNCTION IF EXISTS enrollment.reject_enrollments_hard_delete()');
        DB::statement('DROP POLICY IF EXISTS enrollment_school_isolation ON enrollment.enrollments');
        DB::statement("
            CREATE POLICY enrollment_school_isolation ON enrollment.enrollments
            USING (
                school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                OR current_setting('app.current_school_id', true) IS NULL
                OR current_setting('app.current_school_id', true) = ''
            )
        ");
        DB::statement('ALTER TABLE enrollment.enrollments NO FORCE ROW LEVEL SECURITY');
    }
};
