<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase AUDIT-U01 — physicalize audit.audit_logs (append-only + FORCE RLS).
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
            CREATE TABLE IF NOT EXISTS audit.audit_logs (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                user_id BIGINT,
                action VARCHAR(50) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id BIGINT,
                old_values JSONB,
                new_values JSONB,
                ip_address INET,
                user_agent TEXT,
                correlation_id VARCHAR(100),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT audit_audit_logs_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT audit_audit_logs_user_fk
                    FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT audit_audit_logs_action_check
                    CHECK (char_length(trim(action)) > 0),
                CONSTRAINT audit_audit_logs_entity_type_check
                    CHECK (char_length(trim(entity_type)) > 0)
            )
        ");

        DB::statement('
            CREATE INDEX IF NOT EXISTS audit_audit_logs_school_created_idx
            ON audit.audit_logs (school_id, created_at DESC)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS audit_audit_logs_entity_idx
            ON audit.audit_logs (entity_type, entity_id)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS audit_audit_logs_user_created_idx
            ON audit.audit_logs (user_id, created_at DESC)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS audit_audit_logs_correlation_idx
            ON audit.audit_logs (correlation_id)
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION audit.reject_audit_logs_mutation()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Hard DELETE of audit.audit_logs is forbidden — append-only'
                        USING ERRCODE = 'check_violation';
                END IF;
                RAISE EXCEPTION 'UPDATE of audit.audit_logs is forbidden — append-only'
                    USING ERRCODE = 'check_violation';
            END; $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS audit_audit_logs_reject_delete ON audit.audit_logs');
        DB::statement("
            CREATE TRIGGER audit_audit_logs_reject_delete
            BEFORE DELETE ON audit.audit_logs
            FOR EACH ROW
            EXECUTE FUNCTION audit.reject_audit_logs_mutation()
        ");
        DB::statement('DROP TRIGGER IF EXISTS audit_audit_logs_reject_update ON audit.audit_logs');
        DB::statement("
            CREATE TRIGGER audit_audit_logs_reject_update
            BEFORE UPDATE ON audit.audit_logs
            FOR EACH ROW
            EXECUTE FUNCTION audit.reject_audit_logs_mutation()
        ");

        DB::statement('ALTER TABLE audit.audit_logs ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE audit.audit_logs FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS audit_logs_school_isolation ON audit.audit_logs');
        DB::statement("
            CREATE POLICY audit_logs_school_isolation ON audit.audit_logs
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

        DB::statement('DROP POLICY IF EXISTS audit_logs_school_isolation ON audit.audit_logs');
        DB::statement('ALTER TABLE audit.audit_logs NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE audit.audit_logs DISABLE ROW LEVEL SECURITY');
        DB::statement('DROP TRIGGER IF EXISTS audit_audit_logs_reject_update ON audit.audit_logs');
        DB::statement('DROP TRIGGER IF EXISTS audit_audit_logs_reject_delete ON audit.audit_logs');
        DB::statement('DROP FUNCTION IF EXISTS audit.reject_audit_logs_mutation()');
        DB::statement('DROP TABLE IF EXISTS audit.audit_logs');
    }
};
