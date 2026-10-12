<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Attendance & Absence Management — sheet review lifecycle, policies, excuses, staff attendance, alerts.
 *
 * Additive only (no column drops, no data rewrite):
 *   attendance.sessions   + sheet lifecycle (sheet_status / version / submit + review stamps), timetable link
 *                           (schedule_id, group_id, lesson_type, frozen lesson_snapshot), governing policy_id.
 *                           The OPEN natural-key unique gains COALESCE(schedule_id, 0) so two timetable lessons of one
 *                           section · subject · period (practical groups) can both be open; legacy rows unchanged.
 *   attendance.records    + minutes_late, marked_via (evidence method), excuse_id; CHECK status 0..5
 *                           (0 unmarked draft · 1 present · 2 absent · 3 late · 4 excused · 5 left early).
 *
 * New tables (FORCE RLS fail-closed on school_id, hard DELETE rejected by trigger):
 *   attendance.policies        versioned per school + year (history kept — sessions point at the governing row)
 *   attendance.sheet_events    immutable review history per session
 *   attendance.excuses         absence excuses: pending → approved / rejected / withdrawn
 *   attendance.staff_records   teacher work attendance (one row per teacher · school · day)
 *   attendance.checkin_codes   short-lived hashed check-in codes (staff self check-in evidence)
 *   attendance.alerts          rule-based alerts for human review (never automatic decisions)
 */
return new class extends Migration
{
    private const NEW_TABLES = ['policies', 'sheet_events', 'excuses', 'staff_records', 'checkin_codes', 'alerts'];

    private const RLS_PREDICATE = "
        NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
        AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
    ";

    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        $invalid = (int) DB::table('attendance.records')->whereNotBetween('status', [1, 5])->count();
        if ($invalid > 0) {
            throw new RuntimeException("Attendance lifecycle cannot add the records status CHECK: {$invalid} row(s) outside 1..5.");
        }

        $this->createPolicies();
        $this->extendSessions();
        $this->createSheetEvents();
        $this->createExcuses();
        $this->extendRecords();
        $this->createStaffRecords();
        $this->createCheckinCodes();
        $this->createAlerts();

        foreach (self::NEW_TABLES as $table) {
            $this->enableRls($table);
            $this->rejectDelete($table);
        }
        // Review history is append-only.
        DB::statement("
            CREATE OR REPLACE FUNCTION attendance.reject_sheet_events_update()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'attendance.sheet_events is append-only';
            END; $$
        ");
        DB::statement('
            CREATE TRIGGER sheet_events_reject_update
            BEFORE UPDATE ON attendance.sheet_events
            FOR EACH ROW EXECUTE FUNCTION attendance.reject_sheet_events_update()
        ');
    }

    private function createPolicies(): void
    {
        DB::statement("
            CREATE TABLE attendance.policies (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                academic_year_id BIGINT NOT NULL REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                version_no INTEGER NOT NULL CHECK (version_no >= 1),
                timezone VARCHAR(64) NOT NULL,
                open_before_minutes SMALLINT NOT NULL DEFAULT 10 CHECK (open_before_minutes BETWEEN 0 AND 240),
                submit_deadline_minutes SMALLINT NOT NULL DEFAULT 120 CHECK (submit_deadline_minutes BETWEEN 0 AND 1440),
                edit_window_days SMALLINT NOT NULL DEFAULT 0 CHECK (edit_window_days BETWEEN 0 AND 30),
                late_grace_minutes SMALLINT NOT NULL DEFAULT 5 CHECK (late_grace_minutes BETWEEN 0 AND 120),
                late_as_absent_minutes SMALLINT CHECK (late_as_absent_minutes IS NULL OR late_as_absent_minutes BETWEEN 1 AND 240),
                require_complete BOOLEAN NOT NULL DEFAULT TRUE,
                default_mark SMALLINT NOT NULL DEFAULT 0 CHECK (default_mark IN (0, 1)),
                teacher_can_mark_excused BOOLEAN NOT NULL DEFAULT FALSE,
                absence_alert_count SMALLINT NOT NULL DEFAULT 3 CHECK (absence_alert_count BETWEEN 1 AND 100),
                absence_alert_window_days SMALLINT NOT NULL DEFAULT 14 CHECK (absence_alert_window_days BETWEEN 1 AND 180),
                absence_rate_alert_percent SMALLINT NOT NULL DEFAULT 20 CHECK (absence_rate_alert_percent BETWEEN 1 AND 100),
                late_alert_count SMALLINT NOT NULL DEFAULT 3 CHECK (late_alert_count BETWEEN 1 AND 100),
                returned_alert_count SMALLINT NOT NULL DEFAULT 3 CHECK (returned_alert_count BETWEEN 1 AND 100),
                staff_day_start TIME NOT NULL DEFAULT '07:30',
                staff_late_grace_minutes SMALLINT NOT NULL DEFAULT 10 CHECK (staff_late_grace_minutes BETWEEN 0 AND 240),
                checkin_code_ttl_minutes SMALLINT NOT NULL DEFAULT 10 CHECK (checkin_code_ttl_minutes BETWEEN 1 AND 120),
                effective_from TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                effective_to TIMESTAMPTZ,
                created_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT policies_effective_check CHECK (effective_to IS NULL OR effective_to >= effective_from)
            )
        ");
        DB::statement('CREATE UNIQUE INDEX policies_school_year_version_uidx ON attendance.policies (school_id, academic_year_id, version_no)');
        DB::statement('CREATE UNIQUE INDEX policies_current_uidx ON attendance.policies (school_id, academic_year_id) WHERE effective_to IS NULL');
    }

    private function extendSessions(): void
    {
        DB::statement('
            ALTER TABLE attendance.sessions
                ADD COLUMN sheet_status SMALLINT NOT NULL DEFAULT 1,
                ADD COLUMN version INTEGER NOT NULL DEFAULT 1,
                ADD COLUMN origin SMALLINT NOT NULL DEFAULT 1,
                ADD COLUMN schedule_id BIGINT REFERENCES timetable.schedules(id) ON DELETE RESTRICT,
                ADD COLUMN group_id BIGINT REFERENCES timetable.division_groups(id) ON DELETE RESTRICT,
                ADD COLUMN lesson_type SMALLINT,
                ADD COLUMN lesson_snapshot JSONB,
                ADD COLUMN policy_id BIGINT REFERENCES attendance.policies(id) ON DELETE RESTRICT,
                ADD COLUMN submission_count SMALLINT NOT NULL DEFAULT 0,
                ADD COLUMN submitted_at TIMESTAMPTZ,
                ADD COLUMN submitted_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                ADD COLUMN submitted_late BOOLEAN NOT NULL DEFAULT FALSE,
                ADD COLUMN reviewed_at TIMESTAMPTZ,
                ADD COLUMN reviewed_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                ADD COLUMN review_note TEXT,
                ADD COLUMN opened_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                ADD COLUMN updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        ');
        DB::statement('ALTER TABLE attendance.sessions ADD CONSTRAINT attendance_sessions_sheet_status_check CHECK (sheet_status BETWEEN 1 AND 6)');
        DB::statement('ALTER TABLE attendance.sessions ADD CONSTRAINT attendance_sessions_origin_check CHECK (origin IN (1, 2, 3))');
        DB::statement('ALTER TABLE attendance.sessions ADD CONSTRAINT attendance_sessions_lesson_type_check CHECK (lesson_type IS NULL OR lesson_type IN (1, 2))');
        DB::statement('ALTER TABLE attendance.sessions ADD CONSTRAINT attendance_sessions_version_check CHECK (version >= 1 AND submission_count >= 0)');

        // One live (non-cancelled) sheet per timetable lesson and date.
        DB::statement('
            CREATE UNIQUE INDEX attendance_sessions_schedule_date_uidx
            ON attendance.sessions (school_id, schedule_id, session_date)
            WHERE schedule_id IS NOT NULL AND status <> 3
        ');
        DB::statement('DROP INDEX IF EXISTS attendance.attendance_sessions_open_natural_key_uidx');
        DB::statement('
            CREATE UNIQUE INDEX attendance_sessions_open_natural_key_uidx
            ON attendance.sessions (school_id, academic_year_id, section_id, subject_id, session_date, period_id, COALESCE(schedule_id, 0))
            NULLS NOT DISTINCT
            WHERE status = 1
        ');
        // Review board and teacher day reads.
        DB::statement('CREATE INDEX attendance_sessions_review_idx ON attendance.sessions (school_id, sheet_status, session_date)');
        DB::statement('CREATE INDEX attendance_sessions_teacher_date_idx ON attendance.sessions (teacher_id, session_date)');
    }

    private function createSheetEvents(): void
    {
        DB::statement('
            CREATE TABLE attendance.sheet_events (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                academic_year_id BIGINT NOT NULL REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                session_id BIGINT NOT NULL REFERENCES attendance.sessions(id) ON DELETE RESTRICT,
                event_type SMALLINT NOT NULL CHECK (event_type BETWEEN 1 AND 9),
                from_status SMALLINT,
                to_status SMALLINT NOT NULL,
                version INTEGER NOT NULL,
                actor_user_id BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                note TEXT,
                counts JSONB,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ');
        DB::statement('CREATE INDEX sheet_events_session_idx ON attendance.sheet_events (session_id, id)');
        DB::statement('CREATE INDEX sheet_events_school_created_idx ON attendance.sheet_events (school_id, created_at DESC)');
    }

    private function createExcuses(): void
    {
        DB::statement("
            CREATE TABLE attendance.excuses (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                academic_year_id BIGINT NOT NULL REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                student_id BIGINT NOT NULL REFERENCES students.students(id) ON DELETE RESTRICT,
                enrollment_id BIGINT NOT NULL REFERENCES enrollment.enrollments(id) ON DELETE RESTRICT,
                date_from DATE NOT NULL,
                date_to DATE NOT NULL,
                session_id BIGINT REFERENCES attendance.sessions(id) ON DELETE RESTRICT,
                excuse_type SMALLINT NOT NULL CHECK (excuse_type BETWEEN 1 AND 9),
                reason TEXT NOT NULL CHECK (char_length(trim(reason)) BETWEEN 1 AND 2000),
                document_file_id BIGINT REFERENCES documents.files(id) ON DELETE RESTRICT,
                status SMALLINT NOT NULL DEFAULT 1 CHECK (status BETWEEN 1 AND 4),
                submitted_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                submitted_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                reviewed_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                reviewed_at TIMESTAMPTZ,
                decision_note TEXT,
                applied_records INTEGER NOT NULL DEFAULT 0,
                version INTEGER NOT NULL DEFAULT 1 CHECK (version >= 1),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT excuses_range_check CHECK (date_to >= date_from AND date_to - date_from <= 90),
                CONSTRAINT excuses_rejection_note_check CHECK (status <> 3 OR char_length(trim(coalesce(decision_note, ''))) > 0)
            )
        ");
        DB::statement('CREATE INDEX excuses_school_status_idx ON attendance.excuses (school_id, status, date_from)');
        DB::statement('CREATE INDEX excuses_student_idx ON attendance.excuses (student_id, date_from)');
    }

    private function extendRecords(): void
    {
        // Partitioned parent: constraints and nullable / constant-default columns propagate without a rewrite.
        DB::statement('
            ALTER TABLE attendance.records
                ADD COLUMN minutes_late SMALLINT,
                ADD COLUMN marked_via SMALLINT NOT NULL DEFAULT 1,
                ADD COLUMN excuse_id BIGINT REFERENCES attendance.excuses(id) ON DELETE RESTRICT
        ');
        DB::statement('ALTER TABLE attendance.records ADD CONSTRAINT attendance_records_status_check CHECK (status BETWEEN 0 AND 5)');
        DB::statement('ALTER TABLE attendance.records ADD CONSTRAINT attendance_records_minutes_late_check CHECK (minutes_late IS NULL OR minutes_late BETWEEN 0 AND 600)');
        DB::statement('ALTER TABLE attendance.records ADD CONSTRAINT attendance_records_marked_via_check CHECK (marked_via BETWEEN 1 AND 9)');
        // Session roster reads (sheet + review) and per-student excuse application.
        DB::statement('CREATE INDEX IF NOT EXISTS attendance_records_session_idx ON attendance.records (session_id)');
    }

    private function createStaffRecords(): void
    {
        DB::statement('
            CREATE TABLE attendance.staff_records (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                academic_year_id BIGINT NOT NULL REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                teacher_id BIGINT NOT NULL REFERENCES teachers.teachers(id) ON DELETE RESTRICT,
                work_date DATE NOT NULL,
                status SMALLINT NOT NULL CHECK (status BETWEEN 1 AND 7),
                check_in_at TIMESTAMPTZ,
                check_out_at TIMESTAMPTZ,
                minutes_late SMALLINT CHECK (minutes_late IS NULL OR minutes_late BETWEEN 0 AND 1440),
                method SMALLINT NOT NULL CHECK (method BETWEEN 1 AND 9),
                notes TEXT,
                recorded_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                version INTEGER NOT NULL DEFAULT 1 CHECK (version >= 1),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT staff_records_checkout_check CHECK (check_out_at IS NULL OR check_in_at IS NULL OR check_out_at >= check_in_at)
            )
        ');
        DB::statement('CREATE UNIQUE INDEX staff_records_teacher_day_uidx ON attendance.staff_records (school_id, teacher_id, work_date)');
        DB::statement('CREATE INDEX staff_records_school_date_idx ON attendance.staff_records (school_id, work_date)');
    }

    private function createCheckinCodes(): void
    {
        DB::statement('
            CREATE TABLE attendance.checkin_codes (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                code_hash VARCHAR(128) NOT NULL,
                valid_from TIMESTAMPTZ NOT NULL,
                valid_until TIMESTAMPTZ NOT NULL,
                revoked_at TIMESTAMPTZ,
                created_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT checkin_codes_window_check CHECK (valid_until > valid_from)
            )
        ');
        DB::statement('CREATE INDEX checkin_codes_school_valid_idx ON attendance.checkin_codes (school_id, valid_until)');
    }

    private function createAlerts(): void
    {
        DB::statement("
            CREATE TABLE attendance.alerts (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                academic_year_id BIGINT NOT NULL REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                rule_code VARCHAR(50) NOT NULL,
                severity SMALLINT NOT NULL DEFAULT 1 CHECK (severity BETWEEN 1 AND 3),
                subject_type VARCHAR(20) NOT NULL CHECK (subject_type IN ('student', 'teacher', 'session', 'section', 'subject')),
                subject_id BIGINT NOT NULL,
                period_start DATE NOT NULL,
                period_end DATE NOT NULL,
                metric NUMERIC(8, 2) NOT NULL,
                threshold NUMERIC(8, 2) NOT NULL,
                details JSONB,
                status SMALLINT NOT NULL DEFAULT 1 CHECK (status BETWEEN 1 AND 4),
                resolved_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                resolved_at TIMESTAMPTZ,
                resolution_note TEXT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT alerts_period_check CHECK (period_end >= period_start)
            )
        ");
        DB::statement('CREATE UNIQUE INDEX alerts_dedupe_uidx ON attendance.alerts (school_id, rule_code, subject_type, subject_id, period_end)');
        DB::statement('CREATE INDEX alerts_school_status_idx ON attendance.alerts (school_id, status, created_at DESC)');
    }

    private function enableRls(string $table): void
    {
        DB::statement("ALTER TABLE attendance.{$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE attendance.{$table} FORCE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY attendance_{$table}_school_select ON attendance.{$table} FOR SELECT USING (".self::RLS_PREDICATE.')');
        DB::statement("CREATE POLICY attendance_{$table}_school_insert ON attendance.{$table} FOR INSERT WITH CHECK (".self::RLS_PREDICATE.')');
        DB::statement("CREATE POLICY attendance_{$table}_school_update ON attendance.{$table} FOR UPDATE USING (".self::RLS_PREDICATE.') WITH CHECK ('.self::RLS_PREDICATE.')');
    }

    private function rejectDelete(string $table): void
    {
        DB::statement("
            CREATE OR REPLACE FUNCTION attendance.reject_{$table}_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of attendance.{$table} is forbidden';
            END; $$
        ");
        DB::statement("
            CREATE TRIGGER {$table}_reject_delete
            BEFORE DELETE ON attendance.{$table}
            FOR EACH ROW EXECUTE FUNCTION attendance.reject_{$table}_delete()
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS attendance.attendance_records_session_idx');
        DB::statement('ALTER TABLE attendance.records DROP CONSTRAINT IF EXISTS attendance_records_marked_via_check');
        DB::statement('ALTER TABLE attendance.records DROP CONSTRAINT IF EXISTS attendance_records_minutes_late_check');
        DB::statement('ALTER TABLE attendance.records DROP CONSTRAINT IF EXISTS attendance_records_status_check');
        DB::statement('ALTER TABLE attendance.records DROP COLUMN IF EXISTS excuse_id, DROP COLUMN IF EXISTS marked_via, DROP COLUMN IF EXISTS minutes_late');

        DB::statement('DROP INDEX IF EXISTS attendance.attendance_sessions_teacher_date_idx');
        DB::statement('DROP INDEX IF EXISTS attendance.attendance_sessions_review_idx');
        DB::statement('DROP INDEX IF EXISTS attendance.attendance_sessions_schedule_date_uidx');
        DB::statement('DROP INDEX IF EXISTS attendance.attendance_sessions_open_natural_key_uidx');
        DB::statement('
            CREATE UNIQUE INDEX attendance_sessions_open_natural_key_uidx
            ON attendance.sessions (school_id, academic_year_id, section_id, subject_id, session_date, period_id)
            NULLS NOT DISTINCT
            WHERE status = 1
        ');

        DB::statement('DROP TABLE IF EXISTS attendance.sheet_events');
        DB::statement('DROP FUNCTION IF EXISTS attendance.reject_sheet_events_update()');
        foreach (['alerts', 'checkin_codes', 'staff_records', 'excuses'] as $table) {
            DB::statement("DROP TABLE IF EXISTS attendance.{$table}");
        }

        foreach (['attendance_sessions_version_check', 'attendance_sessions_lesson_type_check', 'attendance_sessions_origin_check', 'attendance_sessions_sheet_status_check'] as $check) {
            DB::statement("ALTER TABLE attendance.sessions DROP CONSTRAINT IF EXISTS {$check}");
        }
        DB::statement('
            ALTER TABLE attendance.sessions
                DROP COLUMN IF EXISTS updated_at, DROP COLUMN IF EXISTS opened_by, DROP COLUMN IF EXISTS review_note,
                DROP COLUMN IF EXISTS reviewed_by, DROP COLUMN IF EXISTS reviewed_at, DROP COLUMN IF EXISTS submitted_late,
                DROP COLUMN IF EXISTS submitted_by, DROP COLUMN IF EXISTS submitted_at, DROP COLUMN IF EXISTS submission_count,
                DROP COLUMN IF EXISTS policy_id, DROP COLUMN IF EXISTS lesson_snapshot, DROP COLUMN IF EXISTS lesson_type,
                DROP COLUMN IF EXISTS group_id, DROP COLUMN IF EXISTS schedule_id, DROP COLUMN IF EXISTS origin,
                DROP COLUMN IF EXISTS version, DROP COLUMN IF EXISTS sheet_status
        ');
        DB::statement('DROP TABLE IF EXISTS attendance.policies');

        foreach (self::NEW_TABLES as $table) {
            DB::statement("DROP FUNCTION IF EXISTS attendance.reject_{$table}_delete()");
        }
    }
};
