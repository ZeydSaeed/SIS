<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase COM-U07 — physicalize communication.notification_jobs (+ school_id; catalog only).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS communication');

        DB::statement("
            CREATE TABLE IF NOT EXISTS communication.notification_jobs (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                job_id UUID NOT NULL DEFAULT gen_random_uuid(),
                template_id BIGINT NOT NULL,
                target_filter JSONB NOT NULL,
                total_count INTEGER NOT NULL DEFAULT 0,
                sent_count INTEGER NOT NULL DEFAULT 0,
                status SMALLINT NOT NULL DEFAULT 1,
                idempotency_key VARCHAR(100) NOT NULL,
                created_by BIGINT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                completed_at TIMESTAMPTZ,
                CONSTRAINT communication_notification_jobs_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT communication_notification_jobs_template_fk
                    FOREIGN KEY (template_id) REFERENCES communication.notification_templates(id) ON DELETE RESTRICT,
                CONSTRAINT communication_notification_jobs_created_by_fk
                    FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT communication_notification_jobs_job_id_uq
                    UNIQUE (job_id),
                CONSTRAINT communication_notification_jobs_idempotency_uq
                    UNIQUE (idempotency_key),
                CONSTRAINT communication_notification_jobs_status_check
                    CHECK (status IN (1, 2, 3)),
                CONSTRAINT communication_notification_jobs_counts_check
                    CHECK (total_count >= 0 AND sent_count >= 0 AND sent_count <= total_count)
            )
        ");

        DB::statement('
            CREATE INDEX IF NOT EXISTS communication_notification_jobs_school_idx
            ON communication.notification_jobs (school_id)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS communication_notification_jobs_school_status_idx
            ON communication.notification_jobs (school_id, status)
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION communication.reject_notification_jobs_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard DELETE of communication.notification_jobs is forbidden'
                    USING ERRCODE = 'check_violation';
            END; $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS notification_jobs_reject_delete ON communication.notification_jobs');
        DB::statement("
            CREATE TRIGGER notification_jobs_reject_delete
            BEFORE DELETE ON communication.notification_jobs
            FOR EACH ROW
            EXECUTE FUNCTION communication.reject_notification_jobs_delete()
        ");

        DB::statement('ALTER TABLE communication.notification_jobs ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE communication.notification_jobs FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS notification_jobs_school_isolation ON communication.notification_jobs');
        DB::statement("
            CREATE POLICY notification_jobs_school_isolation ON communication.notification_jobs
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS notification_jobs_school_isolation ON communication.notification_jobs');
        DB::statement('ALTER TABLE communication.notification_jobs NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE communication.notification_jobs DISABLE ROW LEVEL SECURITY');
        DB::statement('DROP TRIGGER IF EXISTS notification_jobs_reject_delete ON communication.notification_jobs');
        DB::statement('DROP FUNCTION IF EXISTS communication.reject_notification_jobs_delete()');
        DB::statement('DROP TABLE IF EXISTS communication.notification_jobs');
    }
};
