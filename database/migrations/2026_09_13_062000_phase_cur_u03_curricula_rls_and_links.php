<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase CUR-U03 — curricula FORCE RLS + curriculum_subjects soft status/RLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            ALTER TABLE curriculum.curricula
            DROP CONSTRAINT IF EXISTS curriculum_curricula_status_check
        ');
        DB::statement('
            ALTER TABLE curriculum.curricula
            ADD CONSTRAINT curriculum_curricula_status_check
            CHECK (status IN (1, 2))
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION curriculum.reject_curricula_hard_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard DELETE of curriculum.curricula is forbidden — soft-deactivate via status'
                    USING ERRCODE = 'check_violation';
            END; $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS curriculum_curricula_reject_hard_delete ON curriculum.curricula');
        DB::statement("
            CREATE TRIGGER curriculum_curricula_reject_hard_delete
            BEFORE DELETE ON curriculum.curricula
            FOR EACH ROW
            EXECUTE FUNCTION curriculum.reject_curricula_hard_delete()
        ");

        DB::statement('ALTER TABLE curriculum.curricula ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE curriculum.curricula FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS curricula_school_isolation ON curriculum.curricula');
        DB::statement("
            CREATE POLICY curricula_school_isolation ON curriculum.curricula
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");

        DB::statement('
            ALTER TABLE curriculum.curriculum_subjects
            ADD COLUMN IF NOT EXISTS status SMALLINT NOT NULL DEFAULT 1
        ');
        DB::statement('
            ALTER TABLE curriculum.curriculum_subjects
            DROP CONSTRAINT IF EXISTS curriculum_curriculum_subjects_status_check
        ');
        DB::statement('
            ALTER TABLE curriculum.curriculum_subjects
            ADD CONSTRAINT curriculum_curriculum_subjects_status_check
            CHECK (status IN (1, 2))
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS curriculum_curriculum_subjects_curriculum_status_idx
            ON curriculum.curriculum_subjects (curriculum_id, status)
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION curriculum.reject_curriculum_subjects_hard_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard DELETE of curriculum.curriculum_subjects is forbidden — soft-deactivate via status'
                    USING ERRCODE = 'check_violation';
            END; $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS curriculum_curriculum_subjects_reject_hard_delete ON curriculum.curriculum_subjects');
        DB::statement("
            CREATE TRIGGER curriculum_curriculum_subjects_reject_hard_delete
            BEFORE DELETE ON curriculum.curriculum_subjects
            FOR EACH ROW
            EXECUTE FUNCTION curriculum.reject_curriculum_subjects_hard_delete()
        ");

        DB::statement('ALTER TABLE curriculum.curriculum_subjects ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE curriculum.curriculum_subjects FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS curriculum_subjects_school_isolation ON curriculum.curriculum_subjects');
        DB::statement("
            CREATE POLICY curriculum_subjects_school_isolation ON curriculum.curriculum_subjects
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM curriculum.curricula c
                    WHERE c.id = curriculum_subjects.curriculum_id
                      AND c.school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM curriculum.curricula c
                    WHERE c.id = curriculum_subjects.curriculum_id
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

        DB::statement('DROP POLICY IF EXISTS curriculum_subjects_school_isolation ON curriculum.curriculum_subjects');
        DB::statement('ALTER TABLE curriculum.curriculum_subjects DISABLE ROW LEVEL SECURITY');
        DB::statement('DROP TRIGGER IF EXISTS curriculum_curriculum_subjects_reject_hard_delete ON curriculum.curriculum_subjects');
        DB::statement('DROP FUNCTION IF EXISTS curriculum.reject_curriculum_subjects_hard_delete()');
        DB::statement('ALTER TABLE curriculum.curriculum_subjects DROP CONSTRAINT IF EXISTS curriculum_curriculum_subjects_status_check');
        DB::statement('ALTER TABLE curriculum.curriculum_subjects DROP COLUMN IF EXISTS status');

        DB::statement('DROP POLICY IF EXISTS curricula_school_isolation ON curriculum.curricula');
        DB::statement('ALTER TABLE curriculum.curricula DISABLE ROW LEVEL SECURITY');
        DB::statement('DROP TRIGGER IF EXISTS curriculum_curricula_reject_hard_delete ON curriculum.curricula');
        DB::statement('DROP FUNCTION IF EXISTS curriculum.reject_curricula_hard_delete()');
        DB::statement('ALTER TABLE curriculum.curricula DROP CONSTRAINT IF EXISTS curriculum_curricula_status_check');
    }
};
