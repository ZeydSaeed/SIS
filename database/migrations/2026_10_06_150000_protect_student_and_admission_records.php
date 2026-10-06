<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data-integrity guards for the admission → student chain (PostgreSQL only).
 *
 * - students.students: hard DELETE is forbidden (official record — change status instead).
 * - admission.applications: a row linked to a student (student_id set) cannot be deleted;
 *   drafts and unlinked rows stay deletable for demo/QA wipers.
 * - admission.application_periods: academic_year_id is fixed after insert — applications
 *   inherit their year from the period, so changing it would silently move them.
 *
 * No new columns or indexes; triggers only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement("
            CREATE OR REPLACE FUNCTION students.reject_students_hard_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard DELETE of students.students is forbidden — change status instead'
                    USING ERRCODE = 'check_violation';
            END; $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS students_students_reject_hard_delete ON students.students');
        DB::statement('
            CREATE TRIGGER students_students_reject_hard_delete
            BEFORE DELETE ON students.students
            FOR EACH ROW
            EXECUTE FUNCTION students.reject_students_hard_delete()
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION admission.reject_converted_application_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.student_id IS NOT NULL THEN
                    RAISE EXCEPTION 'admission.applications row % is linked to a student and cannot be deleted', OLD.id
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN OLD;
            END; $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS admission_applications_reject_converted_delete ON admission.applications');
        DB::statement('
            CREATE TRIGGER admission_applications_reject_converted_delete
            BEFORE DELETE ON admission.applications
            FOR EACH ROW
            EXECUTE FUNCTION admission.reject_converted_application_delete()
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION admission.lock_application_period_academic_year()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.academic_year_id IS DISTINCT FROM OLD.academic_year_id THEN
                    RAISE EXCEPTION 'academic_year_id of admission.application_periods row % cannot change', OLD.id
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END; $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS admission_application_periods_lock_academic_year ON admission.application_periods');
        DB::statement('
            CREATE TRIGGER admission_application_periods_lock_academic_year
            BEFORE UPDATE OF academic_year_id ON admission.application_periods
            FOR EACH ROW
            EXECUTE FUNCTION admission.lock_application_period_academic_year()
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS admission_application_periods_lock_academic_year ON admission.application_periods');
        DB::statement('DROP FUNCTION IF EXISTS admission.lock_application_period_academic_year()');
        DB::statement('DROP TRIGGER IF EXISTS admission_applications_reject_converted_delete ON admission.applications');
        DB::statement('DROP FUNCTION IF EXISTS admission.reject_converted_application_delete()');
        DB::statement('DROP TRIGGER IF EXISTS students_students_reject_hard_delete ON students.students');
        DB::statement('DROP FUNCTION IF EXISTS students.reject_students_hard_delete()');
    }
};
