<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase AUDIT-U02 — physicalize audit.login_history (append-only + FORCE RLS).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS audit');

        DB::statement("
            CREATE TABLE IF NOT EXISTS audit.login_history (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                user_id BIGINT NOT NULL,
                ip_address INET,
                user_agent TEXT,
                login_status SMALLINT NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT audit_login_history_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT audit_login_history_user_fk
                    FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT,
                CONSTRAINT audit_login_history_status_check
                    CHECK (login_status IN (1, 2))
            )
        ");

        DB::statement('
            CREATE INDEX IF NOT EXISTS audit_login_history_school_created_idx
            ON audit.login_history (school_id, created_at DESC)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS audit_login_history_user_created_idx
            ON audit.login_history (user_id, created_at DESC)
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION audit.reject_login_history_mutation()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Hard DELETE of audit.login_history is forbidden — append-only'
                        USING ERRCODE = 'check_violation';
                END IF;
                RAISE EXCEPTION 'UPDATE of audit.login_history is forbidden — append-only'
                    USING ERRCODE = 'check_violation';
            END; $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS audit_login_history_reject_delete ON audit.login_history');
        DB::statement("
            CREATE TRIGGER audit_login_history_reject_delete
            BEFORE DELETE ON audit.login_history
            FOR EACH ROW
            EXECUTE FUNCTION audit.reject_login_history_mutation()
        ");
        DB::statement('DROP TRIGGER IF EXISTS audit_login_history_reject_update ON audit.login_history');
        DB::statement("
            CREATE TRIGGER audit_login_history_reject_update
            BEFORE UPDATE ON audit.login_history
            FOR EACH ROW
            EXECUTE FUNCTION audit.reject_login_history_mutation()
        ");

        DB::statement('ALTER TABLE audit.login_history ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE audit.login_history FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS login_history_school_isolation ON audit.login_history');
        DB::statement("
            CREATE POLICY login_history_school_isolation ON audit.login_history
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

        DB::statement('DROP POLICY IF EXISTS login_history_school_isolation ON audit.login_history');
        DB::statement('ALTER TABLE audit.login_history NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE audit.login_history DISABLE ROW LEVEL SECURITY');
        DB::statement('DROP TRIGGER IF EXISTS audit_login_history_reject_update ON audit.login_history');
        DB::statement('DROP TRIGGER IF EXISTS audit_login_history_reject_delete ON audit.login_history');
        DB::statement('DROP FUNCTION IF EXISTS audit.reject_login_history_mutation()');
        DB::statement('DROP TABLE IF EXISTS audit.login_history');
    }
};
