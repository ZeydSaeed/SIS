<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 8-U02 — Allow DELETE on teacher_subjects for subject unlink only.
 * teachers / teacher_schools / teacher_qualifications remain delete-forbidden.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS teacher_subjects_reject_delete ON teachers.teacher_subjects');
        DB::statement('DROP FUNCTION IF EXISTS teachers.reject_teacher_subjects_delete()');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement("
            CREATE OR REPLACE FUNCTION teachers.reject_teacher_subjects_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of teachers.teacher_subjects is forbidden';
            END;
            $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS teacher_subjects_reject_delete ON teachers.teacher_subjects');
        DB::statement("
            CREATE TRIGGER teacher_subjects_reject_delete
            BEFORE DELETE ON teachers.teacher_subjects
            FOR EACH ROW
            EXECUTE FUNCTION teachers.reject_teacher_subjects_delete()
        ");
    }
};
