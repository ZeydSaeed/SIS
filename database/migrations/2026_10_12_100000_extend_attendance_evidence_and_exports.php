<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Attendance — evidence methods and queued exports (additive).
 *
 *   attendance.policies       + qr_enabled (default on), nfc_enabled (default off) — optional evidence methods per school
 *   attendance.checkin_codes  + kind (1 rotating code / QR · 2 NFC station token), label
 *   attendance.report_exports  queued CSV / PDF report files (FORCE RLS fail-closed; no DELETE policy)
 *
 * No location data is collected (decision 2026-10-12).
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
            ALTER TABLE attendance.policies
                ADD COLUMN qr_enabled BOOLEAN NOT NULL DEFAULT TRUE,
                ADD COLUMN nfc_enabled BOOLEAN NOT NULL DEFAULT FALSE
        ');
        DB::statement('
            ALTER TABLE attendance.checkin_codes
                ADD COLUMN kind SMALLINT NOT NULL DEFAULT 1,
                ADD COLUMN label VARCHAR(100)
        ');
        DB::statement('ALTER TABLE attendance.checkin_codes ADD CONSTRAINT checkin_codes_kind_check CHECK (kind IN (1, 2))');

        DB::statement("
            CREATE TABLE attendance.report_exports (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                academic_year_id BIGINT NOT NULL REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                requested_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                report_type VARCHAR(30) NOT NULL,
                format SMALLINT NOT NULL CHECK (format IN (1, 2)),
                filters JSONB NOT NULL DEFAULT '{}'::jsonb,
                status SMALLINT NOT NULL DEFAULT 1 CHECK (status BETWEEN 1 AND 4),
                row_count INTEGER,
                truncated BOOLEAN NOT NULL DEFAULT FALSE,
                storage_key VARCHAR(500),
                error TEXT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                finished_at TIMESTAMPTZ
            )
        ");
        DB::statement('CREATE INDEX report_exports_school_requester_idx ON attendance.report_exports (school_id, requested_by, created_at DESC)');
        DB::statement('ALTER TABLE attendance.report_exports ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE attendance.report_exports FORCE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY attendance_report_exports_school_select ON attendance.report_exports FOR SELECT USING ('.self::RLS_PREDICATE.')');
        DB::statement('CREATE POLICY attendance_report_exports_school_insert ON attendance.report_exports FOR INSERT WITH CHECK ('.self::RLS_PREDICATE.')');
        DB::statement('CREATE POLICY attendance_report_exports_school_update ON attendance.report_exports FOR UPDATE USING ('.self::RLS_PREDICATE.') WITH CHECK ('.self::RLS_PREDICATE.')');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS attendance.report_exports');
        DB::statement('ALTER TABLE attendance.checkin_codes DROP CONSTRAINT IF EXISTS checkin_codes_kind_check');
        DB::statement('ALTER TABLE attendance.checkin_codes DROP COLUMN IF EXISTS label, DROP COLUMN IF EXISTS kind');
        DB::statement('ALTER TABLE attendance.policies DROP COLUMN IF EXISTS nfc_enabled, DROP COLUMN IF EXISTS qr_enabled');
    }
};
