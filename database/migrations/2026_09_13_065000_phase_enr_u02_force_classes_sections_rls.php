<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase ENR-U02 — FORCE RLS + reject hard DELETE on enrollment.classes / sections.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement("
            CREATE OR REPLACE FUNCTION enrollment.reject_classes_hard_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard DELETE of enrollment.classes is forbidden — soft-deactivate via status'
                    USING ERRCODE = 'check_violation';
            END; $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS enrollment_classes_reject_hard_delete ON enrollment.classes');
        DB::statement("
            CREATE TRIGGER enrollment_classes_reject_hard_delete
            BEFORE DELETE ON enrollment.classes
            FOR EACH ROW
            EXECUTE FUNCTION enrollment.reject_classes_hard_delete()
        ");

        DB::statement("
            CREATE OR REPLACE FUNCTION enrollment.reject_sections_hard_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard DELETE of enrollment.sections is forbidden — soft-deactivate via status'
                    USING ERRCODE = 'check_violation';
            END; $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS enrollment_sections_reject_hard_delete ON enrollment.sections');
        DB::statement("
            CREATE TRIGGER enrollment_sections_reject_hard_delete
            BEFORE DELETE ON enrollment.sections
            FOR EACH ROW
            EXECUTE FUNCTION enrollment.reject_sections_hard_delete()
        ");

        DB::statement('ALTER TABLE enrollment.classes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE enrollment.classes FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS enrollment_classes_school_isolation ON enrollment.classes');
        DB::statement("
            CREATE POLICY enrollment_classes_school_isolation ON enrollment.classes
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");

        DB::statement('ALTER TABLE enrollment.sections ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE enrollment.sections FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS enrollment_sections_school_isolation ON enrollment.sections');
        DB::statement("
            CREATE POLICY enrollment_sections_school_isolation ON enrollment.sections
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM enrollment.classes c
                    WHERE c.id = sections.class_id
                      AND c.school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM enrollment.classes c
                    WHERE c.id = sections.class_id
                      AND c.school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            )
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS enrollment_sections_school_isolation ON enrollment.sections');
        DB::statement('ALTER TABLE enrollment.sections NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE enrollment.sections DISABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS enrollment_classes_school_isolation ON enrollment.classes');
        DB::statement('ALTER TABLE enrollment.classes NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE enrollment.classes DISABLE ROW LEVEL SECURITY');
        DB::statement('DROP TRIGGER IF EXISTS enrollment_sections_reject_hard_delete ON enrollment.sections');
        DB::statement('DROP FUNCTION IF EXISTS enrollment.reject_sections_hard_delete()');
        DB::statement('DROP TRIGGER IF EXISTS enrollment_classes_reject_hard_delete ON enrollment.classes');
        DB::statement('DROP FUNCTION IF EXISTS enrollment.reject_classes_hard_delete()');
    }
};
