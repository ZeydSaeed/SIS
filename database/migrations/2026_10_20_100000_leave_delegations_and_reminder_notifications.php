<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «الإجازات والعطل» › approver delegation and the reminder / holiday notices (2026-10-20). Additive:
 *  - `leave.delegations`: an approver lets another user decide leave in the school between two dates. The delegate
 *    never gets more than the delegator holds (checked when the delegation is used), and never decides their own leave.
 *    FORCE RLS fail-closed on school_id; hard delete rejected; only the revocation columns may change.
 *  - `leave.notifications.kind` widens from 1–6 to 1–9: 7 = holiday changed under an open request, 8 = reminder to the
 *    approvers of a request waiting too long, 9 = escalation to the managers.
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

        DB::statement('ALTER TABLE leave.notifications DROP CONSTRAINT IF EXISTS notifications_kind_check');
        DB::statement('ALTER TABLE leave.notifications ADD CONSTRAINT notifications_kind_check CHECK (kind BETWEEN 1 AND 9)');

        DB::statement("
            CREATE TABLE leave.delegations (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                delegator_user_id BIGINT NOT NULL REFERENCES public.users(id) ON DELETE RESTRICT,
                delegate_user_id BIGINT NOT NULL REFERENCES public.users(id) ON DELETE RESTRICT,
                date_from DATE NOT NULL,
                date_to DATE NOT NULL,
                status SMALLINT NOT NULL DEFAULT 1 CHECK (status IN (1, 2)),
                created_by BIGINT NOT NULL REFERENCES public.users(id) ON DELETE RESTRICT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                revoked_by BIGINT REFERENCES public.users(id) ON DELETE RESTRICT,
                revoked_at TIMESTAMPTZ,
                CONSTRAINT leave_delegations_people_check CHECK (delegator_user_id <> delegate_user_id),
                CONSTRAINT leave_delegations_range_check CHECK (date_to >= date_from AND date_to - date_from <= 366),
                CONSTRAINT leave_delegations_revoke_check CHECK ((status = 2) = (revoked_at IS NOT NULL))
            )
        ");
        // The lookups are «is this user a delegate today» (policy, per request) and «who may decide» (notices).
        DB::statement('CREATE INDEX leave_delegations_delegate_idx ON leave.delegations (school_id, delegate_user_id, date_from, date_to) WHERE status = 1');
        DB::statement('CREATE INDEX leave_delegations_delegator_idx ON leave.delegations (school_id, delegator_user_id, id DESC)');

        DB::statement('ALTER TABLE leave.delegations ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE leave.delegations FORCE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY leave_delegations_school_select ON leave.delegations FOR SELECT USING ('.self::RLS_PREDICATE.')');
        DB::statement('CREATE POLICY leave_delegations_school_insert ON leave.delegations FOR INSERT WITH CHECK ('.self::RLS_PREDICATE.')');
        DB::statement('CREATE POLICY leave_delegations_school_update ON leave.delegations FOR UPDATE USING ('.self::RLS_PREDICATE.') WITH CHECK ('.self::RLS_PREDICATE.')');

        DB::statement("
            CREATE OR REPLACE FUNCTION leave.guard_delegations()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Hard delete of leave.delegations is forbidden';
                END IF;
                IF (NEW.school_id, NEW.delegator_user_id, NEW.delegate_user_id, NEW.date_from, NEW.date_to, NEW.created_by, NEW.created_at)
                    IS DISTINCT FROM (OLD.school_id, OLD.delegator_user_id, OLD.delegate_user_id, OLD.date_from, OLD.date_to, OLD.created_by, OLD.created_at) THEN
                    RAISE EXCEPTION 'leave.delegations: only the revocation may change';
                END IF;
                IF OLD.status = 2 THEN
                    RAISE EXCEPTION 'leave.delegations: a revoked delegation is final';
                END IF;
                RETURN NEW;
            END; $$
        ");
        DB::statement('
            CREATE TRIGGER delegations_guard
            BEFORE UPDATE OR DELETE ON leave.delegations
            FOR EACH ROW EXECUTE FUNCTION leave.guard_delegations()
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        // Destructive (drops the delegation history and narrows the notice kinds): run only with explicit human approval.
        DB::statement('DROP TABLE IF EXISTS leave.delegations');
        DB::statement('DROP FUNCTION IF EXISTS leave.guard_delegations()');
        DB::statement('DELETE FROM leave.notifications WHERE kind > 6');
        DB::statement('ALTER TABLE leave.notifications DROP CONSTRAINT IF EXISTS notifications_kind_check');
        DB::statement('ALTER TABLE leave.notifications ADD CONSTRAINT notifications_kind_check CHECK (kind BETWEEN 1 AND 6)');
    }
};
