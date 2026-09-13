<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase CUR-U02 — harden curriculum.subjects (type CHECK + reject hard DELETE).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            ALTER TABLE curriculum.subjects
            DROP CONSTRAINT IF EXISTS curriculum_subjects_type_check
        ');
        DB::statement('
            ALTER TABLE curriculum.subjects
            ADD CONSTRAINT curriculum_subjects_type_check
            CHECK (subject_type IN (1, 2, 3))
        ');

        DB::statement('
            ALTER TABLE curriculum.subjects
            DROP CONSTRAINT IF EXISTS curriculum_subjects_status_check
        ');
        DB::statement('
            ALTER TABLE curriculum.subjects
            ADD CONSTRAINT curriculum_subjects_status_check
            CHECK (status IN (1, 2))
        ');

        DB::statement('
            ALTER TABLE curriculum.subjects
            DROP CONSTRAINT IF EXISTS curriculum_subjects_grades_check
        ');
        DB::statement('
            ALTER TABLE curriculum.subjects
            ADD CONSTRAINT curriculum_subjects_grades_check
            CHECK (pass_grade >= 0 AND max_grade > 0 AND pass_grade <= max_grade)
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION curriculum.reject_subjects_hard_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Hard DELETE of curriculum.subjects is forbidden — soft-deactivate via status'
                    USING ERRCODE = 'check_violation';
            END;
            $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS curriculum_subjects_reject_hard_delete ON curriculum.subjects');
        DB::statement("
            CREATE TRIGGER curriculum_subjects_reject_hard_delete
            BEFORE DELETE ON curriculum.subjects
            FOR EACH ROW
            EXECUTE FUNCTION curriculum.reject_subjects_hard_delete()
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS curriculum_subjects_reject_hard_delete ON curriculum.subjects');
        DB::statement('DROP FUNCTION IF EXISTS curriculum.reject_subjects_hard_delete()');
        DB::statement('ALTER TABLE curriculum.subjects DROP CONSTRAINT IF EXISTS curriculum_subjects_type_check');
        DB::statement('ALTER TABLE curriculum.subjects DROP CONSTRAINT IF EXISTS curriculum_subjects_status_check');
        DB::statement('ALTER TABLE curriculum.subjects DROP CONSTRAINT IF EXISTS curriculum_subjects_grades_check');
    }
};
