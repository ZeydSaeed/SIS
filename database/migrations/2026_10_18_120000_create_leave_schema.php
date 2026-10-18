<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «الإجازات والغياب» — staff (teachers + administrative employees) leave: types, versioned policies, requests with an
 * approval history, and a balance ledger. Additive only: a new `leave` schema, nothing existing is altered.
 *
 * Reused, not duplicated: student leave = attendance.excuses (its approval already turns absences into excused
 * ones), holidays = academic.holidays, attachments = documents.files (entity_type 'leave_request'), lesson cover =
 * timetable.schedule_exceptions (assigned through the timetable's own substitution command).
 *
 * New tables (FORCE RLS fail-closed on school_id, hard DELETE rejected by trigger):
 *   leave.types            per-school leave types (who may use them, unit, sensitivity)
 *   leave.policies         versioned rules of a type — a request keeps the version it was decided under
 *   leave.requests         one leave request of one teacher or one employee
 *   leave.request_events   append-only history of every action on a request
 *   leave.balance_entries  append-only ledger (entitlement · adjustment · deduction · restoration);
 *                          one deduction / one restoration per request (unique) — never deducted twice
 */
return new class extends Migration
{
    private const TABLES = ['types', 'policies', 'requests', 'request_events', 'balance_entries'];

    private const RLS_PREDICATE = "
        NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
        AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
    ";

    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS leave');
        $this->createTypes();
        $this->createPolicies();
        $this->createRequests();
        $this->createRequestEvents();
        $this->createBalanceEntries();

        foreach (self::TABLES as $table) {
            $this->enableRls($table);
            $this->rejectDelete($table);
        }
        foreach (['request_events', 'balance_entries'] as $table) {
            $this->rejectUpdate($table);
        }
    }

    private function createTypes(): void
    {
        DB::statement("
            CREATE TABLE leave.types (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                code VARCHAR(30) NOT NULL CHECK (code ~ '^[A-Z0-9_]{2,30}$'),
                name_ar VARCHAR(100) NOT NULL CHECK (char_length(trim(name_ar)) BETWEEN 1 AND 100),
                name_en VARCHAR(100),
                description TEXT CHECK (description IS NULL OR char_length(description) <= 2000),
                applies_to SMALLINT NOT NULL CHECK (applies_to IN (1, 2, 3)),
                unit SMALLINT NOT NULL DEFAULT 1 CHECK (unit IN (1, 2)),
                is_sensitive BOOLEAN NOT NULL DEFAULT FALSE,
                color_hue SMALLINT CHECK (color_hue IS NULL OR color_hue BETWEEN 0 AND 359),
                status SMALLINT NOT NULL DEFAULT 1 CHECK (status IN (1, 2)),
                created_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
        DB::statement('CREATE UNIQUE INDEX leave_types_school_code_uidx ON leave.types (school_id, code)');
        DB::statement('CREATE INDEX leave_types_school_status_idx ON leave.types (school_id, status)');
    }

    private function createPolicies(): void
    {
        DB::statement('
            CREATE TABLE leave.policies (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                leave_type_id BIGINT NOT NULL REFERENCES leave.types(id) ON DELETE RESTRICT,
                version_no INTEGER NOT NULL CHECK (version_no >= 1),
                day_basis SMALLINT NOT NULL DEFAULT 1 CHECK (day_basis IN (1, 2)),
                requires_document BOOLEAN NOT NULL DEFAULT FALSE,
                deducts_balance BOOLEAN NOT NULL DEFAULT TRUE,
                approval_levels SMALLINT NOT NULL DEFAULT 1 CHECK (approval_levels BETWEEN 1 AND 3),
                max_per_request NUMERIC(6, 2) CHECK (max_per_request IS NULL OR max_per_request > 0),
                annual_entitlement NUMERIC(6, 2) CHECK (annual_entitlement IS NULL OR annual_entitlement >= 0),
                effective_from DATE NOT NULL,
                effective_to DATE,
                created_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT leave_policies_effective_check CHECK (effective_to IS NULL OR effective_to >= effective_from)
            )
        ');
        DB::statement('CREATE UNIQUE INDEX leave_policies_type_version_uidx ON leave.policies (leave_type_id, version_no)');
        DB::statement('CREATE UNIQUE INDEX leave_policies_current_uidx ON leave.policies (leave_type_id) WHERE effective_to IS NULL');
        DB::statement('CREATE INDEX leave_policies_school_idx ON leave.policies (school_id)');
    }

    private function createRequests(): void
    {
        DB::statement("
            CREATE TABLE leave.requests (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                academic_year_id BIGINT NOT NULL REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                request_no INTEGER NOT NULL CHECK (request_no >= 1),
                requester_kind SMALLINT NOT NULL CHECK (requester_kind IN (1, 2)),
                teacher_id BIGINT REFERENCES teachers.teachers(id) ON DELETE RESTRICT,
                employee_id BIGINT REFERENCES hr.employees(id) ON DELETE RESTRICT,
                leave_type_id BIGINT NOT NULL REFERENCES leave.types(id) ON DELETE RESTRICT,
                policy_id BIGINT NOT NULL REFERENCES leave.policies(id) ON DELETE RESTRICT,
                date_from DATE NOT NULL,
                date_to DATE NOT NULL,
                quantity NUMERIC(6, 2) NOT NULL CHECK (quantity > 0),
                unit SMALLINT NOT NULL CHECK (unit IN (1, 2)),
                reason TEXT NOT NULL CHECK (char_length(trim(reason)) BETWEEN 1 AND 2000),
                status SMALLINT NOT NULL DEFAULT 1 CHECK (status BETWEEN 1 AND 7),
                approval_levels SMALLINT NOT NULL CHECK (approval_levels BETWEEN 1 AND 3),
                approved_levels SMALLINT NOT NULL DEFAULT 0 CHECK (approved_levels >= 0),
                created_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                submitted_at TIMESTAMPTZ,
                decided_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                decided_at TIMESTAMPTZ,
                decision_note TEXT,
                version INTEGER NOT NULL DEFAULT 1 CHECK (version >= 1),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT leave_requests_person_check CHECK (
                    (requester_kind = 1 AND teacher_id IS NOT NULL AND employee_id IS NULL)
                    OR (requester_kind = 2 AND employee_id IS NOT NULL AND teacher_id IS NULL)
                ),
                CONSTRAINT leave_requests_range_check CHECK (date_to >= date_from AND date_to - date_from <= 366),
                CONSTRAINT leave_requests_hours_check CHECK (unit = 1 OR (date_to = date_from AND quantity <= 24)),
                CONSTRAINT leave_requests_levels_check CHECK (approved_levels <= approval_levels),
                CONSTRAINT leave_requests_note_check CHECK (status NOT IN (5, 6) OR char_length(trim(coalesce(decision_note, ''))) > 0)
            )
        ");
        DB::statement('CREATE UNIQUE INDEX leave_requests_school_no_uidx ON leave.requests (school_id, request_no)');
        DB::statement('CREATE INDEX leave_requests_school_status_idx ON leave.requests (school_id, status, date_from)');
        DB::statement('CREATE INDEX leave_requests_school_range_idx ON leave.requests (school_id, date_from, date_to)');
        DB::statement('CREATE INDEX leave_requests_teacher_idx ON leave.requests (teacher_id, date_from) WHERE teacher_id IS NOT NULL');
        DB::statement('CREATE INDEX leave_requests_employee_idx ON leave.requests (employee_id, date_from) WHERE employee_id IS NOT NULL');
    }

    private function createRequestEvents(): void
    {
        DB::statement('
            CREATE TABLE leave.request_events (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                request_id BIGINT NOT NULL REFERENCES leave.requests(id) ON DELETE RESTRICT,
                event_type SMALLINT NOT NULL CHECK (event_type BETWEEN 1 AND 12),
                from_status SMALLINT,
                to_status SMALLINT NOT NULL,
                level SMALLINT,
                actor_user_id BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                note TEXT,
                payload JSONB,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ');
        DB::statement('CREATE INDEX leave_request_events_request_idx ON leave.request_events (request_id, id)');
        DB::statement('CREATE INDEX leave_request_events_school_created_idx ON leave.request_events (school_id, created_at DESC)');
    }

    private function createBalanceEntries(): void
    {
        DB::statement("
            CREATE TABLE leave.balance_entries (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                academic_year_id BIGINT NOT NULL REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                leave_type_id BIGINT NOT NULL REFERENCES leave.types(id) ON DELETE RESTRICT,
                requester_kind SMALLINT NOT NULL CHECK (requester_kind IN (1, 2)),
                teacher_id BIGINT REFERENCES teachers.teachers(id) ON DELETE RESTRICT,
                employee_id BIGINT REFERENCES hr.employees(id) ON DELETE RESTRICT,
                entry_type SMALLINT NOT NULL CHECK (entry_type BETWEEN 1 AND 4),
                quantity NUMERIC(7, 2) NOT NULL,
                request_id BIGINT REFERENCES leave.requests(id) ON DELETE RESTRICT,
                reason TEXT CHECK (reason IS NULL OR char_length(reason) <= 2000),
                created_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT leave_balance_person_check CHECK (
                    (requester_kind = 1 AND teacher_id IS NOT NULL AND employee_id IS NULL)
                    OR (requester_kind = 2 AND employee_id IS NOT NULL AND teacher_id IS NULL)
                ),
                CONSTRAINT leave_balance_entry_check CHECK (
                    (entry_type = 1 AND quantity >= 0 AND request_id IS NULL)
                    OR (entry_type = 2 AND quantity <> 0 AND request_id IS NULL AND char_length(trim(coalesce(reason, ''))) > 0)
                    OR (entry_type = 3 AND quantity < 0 AND request_id IS NOT NULL)
                    OR (entry_type = 4 AND quantity > 0 AND request_id IS NOT NULL)
                )
            )
        ");
        // One deduction and one restoration per request: a retried or concurrent approval can never deduct twice.
        DB::statement('CREATE UNIQUE INDEX leave_balance_request_entry_uidx ON leave.balance_entries (request_id, entry_type) WHERE request_id IS NOT NULL');
        // One annual entitlement per person · type · year.
        DB::statement('CREATE UNIQUE INDEX leave_balance_teacher_entitlement_uidx ON leave.balance_entries (school_id, academic_year_id, leave_type_id, teacher_id) WHERE entry_type = 1 AND teacher_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX leave_balance_employee_entitlement_uidx ON leave.balance_entries (school_id, academic_year_id, leave_type_id, employee_id) WHERE entry_type = 1 AND employee_id IS NOT NULL');
        DB::statement('CREATE INDEX leave_balance_school_year_idx ON leave.balance_entries (school_id, academic_year_id, leave_type_id)');
        DB::statement('CREATE INDEX leave_balance_teacher_idx ON leave.balance_entries (teacher_id, academic_year_id) WHERE teacher_id IS NOT NULL');
        DB::statement('CREATE INDEX leave_balance_employee_idx ON leave.balance_entries (employee_id, academic_year_id) WHERE employee_id IS NOT NULL');
    }

    private function enableRls(string $table): void
    {
        DB::statement("ALTER TABLE leave.{$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE leave.{$table} FORCE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY leave_{$table}_school_select ON leave.{$table} FOR SELECT USING (".self::RLS_PREDICATE.')');
        DB::statement("CREATE POLICY leave_{$table}_school_insert ON leave.{$table} FOR INSERT WITH CHECK (".self::RLS_PREDICATE.')');
        DB::statement("CREATE POLICY leave_{$table}_school_update ON leave.{$table} FOR UPDATE USING (".self::RLS_PREDICATE.') WITH CHECK ('.self::RLS_PREDICATE.')');
    }

    private function rejectDelete(string $table): void
    {
        DB::statement("
            CREATE OR REPLACE FUNCTION leave.reject_{$table}_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of leave.{$table} is forbidden';
            END; $$
        ");
        DB::statement("
            CREATE TRIGGER {$table}_reject_delete
            BEFORE DELETE ON leave.{$table}
            FOR EACH ROW EXECUTE FUNCTION leave.reject_{$table}_delete()
        ");
    }

    private function rejectUpdate(string $table): void
    {
        DB::statement("
            CREATE OR REPLACE FUNCTION leave.reject_{$table}_update()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'leave.{$table} is append-only';
            END; $$
        ");
        DB::statement("
            CREATE TRIGGER {$table}_reject_update
            BEFORE UPDATE ON leave.{$table}
            FOR EACH ROW EXECUTE FUNCTION leave.reject_{$table}_update()
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        // Destructive: drops the leave module with its history — run only with explicit human approval. No CASCADE, so a
        // dependency added later makes it fail loudly instead of dropping more than this schema.
        foreach (array_reverse(self::TABLES) as $table) {
            DB::statement("DROP TABLE IF EXISTS leave.{$table}");
            DB::statement("DROP FUNCTION IF EXISTS leave.reject_{$table}_delete()");
            DB::statement("DROP FUNCTION IF EXISTS leave.reject_{$table}_update()");
        }
        DB::statement('DROP SCHEMA IF EXISTS leave');
    }
};
