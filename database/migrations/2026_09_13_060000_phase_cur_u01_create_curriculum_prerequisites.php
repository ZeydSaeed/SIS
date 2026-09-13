<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase CUR-U01 — physicalize curriculum.prerequisites with soft status.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement("
            CREATE TABLE IF NOT EXISTS curriculum.prerequisites (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                subject_id BIGINT NOT NULL,
                prerequisite_subject_id BIGINT NOT NULL,
                status SMALLINT NOT NULL DEFAULT 1,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT curriculum_prerequisites_subject_fk
                    FOREIGN KEY (subject_id) REFERENCES curriculum.subjects(id) ON DELETE RESTRICT,
                CONSTRAINT curriculum_prerequisites_prereq_fk
                    FOREIGN KEY (prerequisite_subject_id) REFERENCES curriculum.subjects(id) ON DELETE RESTRICT,
                CONSTRAINT curriculum_prerequisites_pair_uq
                    UNIQUE (subject_id, prerequisite_subject_id),
                CONSTRAINT curriculum_prerequisites_self_check
                    CHECK (subject_id <> prerequisite_subject_id),
                CONSTRAINT curriculum_prerequisites_status_check
                    CHECK (status IN (1, 2))
            )
        ");
        DB::statement('CREATE INDEX IF NOT EXISTS curriculum_prerequisites_subject_idx ON curriculum.prerequisites (subject_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS curriculum_prerequisites_subject_status_idx ON curriculum.prerequisites (subject_id, status)');

        DB::statement("
            CREATE OR REPLACE FUNCTION curriculum.reject_prerequisite_hard_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Hard DELETE of curriculum.prerequisites is forbidden — soft-deactivate via status'
                    USING ERRCODE = 'check_violation';
            END;
            $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS curriculum_prerequisites_reject_hard_delete ON curriculum.prerequisites');
        DB::statement("
            CREATE TRIGGER curriculum_prerequisites_reject_hard_delete
            BEFORE DELETE ON curriculum.prerequisites
            FOR EACH ROW
            EXECUTE FUNCTION curriculum.reject_prerequisite_hard_delete()
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS curriculum_prerequisites_reject_hard_delete ON curriculum.prerequisites');
        DB::statement('DROP FUNCTION IF EXISTS curriculum.reject_prerequisite_hard_delete()');
        DB::statement('DROP TABLE IF EXISTS curriculum.prerequisites');
    }
};
