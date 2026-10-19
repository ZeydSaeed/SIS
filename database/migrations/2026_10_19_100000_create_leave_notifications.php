<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «الإجازات والغياب» › in-app notifications (2026-10-19). Additive: one table in the `leave` schema.
 *
 * A notification carries ids and a kind only — never the reason, the type name or any file (the text is composed in
 * the UI from the kind, and the request is re-read through the module's own access rules). One row per recipient.
 * FORCE RLS fail-closed on school_id; hard delete rejected; only `read_at` may change (every other column is frozen).
 */
return new class extends Migration
{
    private const RLS_PREDICATE = "
        NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
        AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
    ";

    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            CREATE TABLE leave.notifications (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                user_id BIGINT NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
                request_id BIGINT NOT NULL REFERENCES leave.requests(id) ON DELETE RESTRICT,
                kind SMALLINT NOT NULL CHECK (kind BETWEEN 1 AND 6),
                actor_user_id BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                read_at TIMESTAMPTZ
            )
        ');
        DB::statement('CREATE INDEX leave_notifications_user_idx ON leave.notifications (user_id, read_at, id DESC)');
        DB::statement('CREATE INDEX leave_notifications_request_idx ON leave.notifications (request_id)');

        DB::statement('ALTER TABLE leave.notifications ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE leave.notifications FORCE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY leave_notifications_school_select ON leave.notifications FOR SELECT USING ('.self::RLS_PREDICATE.')');
        DB::statement('CREATE POLICY leave_notifications_school_insert ON leave.notifications FOR INSERT WITH CHECK ('.self::RLS_PREDICATE.')');
        DB::statement('CREATE POLICY leave_notifications_school_update ON leave.notifications FOR UPDATE USING ('.self::RLS_PREDICATE.') WITH CHECK ('.self::RLS_PREDICATE.')');

        DB::statement("
            CREATE OR REPLACE FUNCTION leave.guard_notifications()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Hard delete of leave.notifications is forbidden';
                END IF;
                IF (NEW.school_id, NEW.user_id, NEW.request_id, NEW.kind, NEW.actor_user_id, NEW.created_at)
                    IS DISTINCT FROM (OLD.school_id, OLD.user_id, OLD.request_id, OLD.kind, OLD.actor_user_id, OLD.created_at) THEN
                    RAISE EXCEPTION 'leave.notifications: only read_at may change';
                END IF;
                RETURN NEW;
            END; $$
        ");
        DB::statement('
            CREATE TRIGGER notifications_guard
            BEFORE UPDATE OR DELETE ON leave.notifications
            FOR EACH ROW EXECUTE FUNCTION leave.guard_notifications()
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        // Destructive (drops the notification history): run only with explicit human approval.
        DB::statement('DROP TABLE IF EXISTS leave.notifications');
        DB::statement('DROP FUNCTION IF EXISTS leave.guard_notifications()');
    }
};
