<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * R1.7 — ENABLE + FORCE RLS on attendance.sessions, records, daily_section_summary.
 * Fail-closed school isolation with explicit WITH CHECK.
 * No DELETE policies → hard DELETE denied under RLS (academic evidence).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS attendance_school_isolation ON attendance.records');
        DB::statement('DROP POLICY IF EXISTS attendance_records_school_select ON attendance.records');
        DB::statement('DROP POLICY IF EXISTS attendance_records_school_insert ON attendance.records');
        DB::statement('DROP POLICY IF EXISTS attendance_records_school_update ON attendance.records');
        DB::statement('DROP POLICY IF EXISTS attendance_sessions_school_select ON attendance.sessions');
        DB::statement('DROP POLICY IF EXISTS attendance_sessions_school_insert ON attendance.sessions');
        DB::statement('DROP POLICY IF EXISTS attendance_sessions_school_update ON attendance.sessions');
        DB::statement('DROP POLICY IF EXISTS attendance_summary_school_select ON attendance.daily_section_summary');
        DB::statement('DROP POLICY IF EXISTS attendance_summary_school_insert ON attendance.daily_section_summary');
        DB::statement('DROP POLICY IF EXISTS attendance_summary_school_update ON attendance.daily_section_summary');

        DB::statement('ALTER TABLE attendance.sessions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE attendance.sessions FORCE ROW LEVEL SECURITY');

        DB::statement('ALTER TABLE attendance.records ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE attendance.records FORCE ROW LEVEL SECURITY');

        DB::statement('ALTER TABLE attendance.daily_section_summary ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE attendance.daily_section_summary FORCE ROW LEVEL SECURITY');

        $predicate = "
            NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
            AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
        ";

        foreach (['sessions', 'records', 'daily_section_summary'] as $table) {
            $prefix = match ($table) {
                'sessions' => 'attendance_sessions',
                'records' => 'attendance_records',
                default => 'attendance_summary',
            };

            DB::statement("
                CREATE POLICY {$prefix}_school_select ON attendance.{$table}
                FOR SELECT
                USING ({$predicate})
            ");

            DB::statement("
                CREATE POLICY {$prefix}_school_insert ON attendance.{$table}
                FOR INSERT
                WITH CHECK ({$predicate})
            ");

            DB::statement("
                CREATE POLICY {$prefix}_school_update ON attendance.{$table}
                FOR UPDATE
                USING ({$predicate})
                WITH CHECK ({$predicate})
            ");
        }
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        foreach (
            [
                'attendance_sessions_school_select',
                'attendance_sessions_school_insert',
                'attendance_sessions_school_update',
            ] as $policy
        ) {
            DB::statement("DROP POLICY IF EXISTS {$policy} ON attendance.sessions");
        }

        foreach (
            [
                'attendance_records_school_select',
                'attendance_records_school_insert',
                'attendance_records_school_update',
            ] as $policy
        ) {
            DB::statement("DROP POLICY IF EXISTS {$policy} ON attendance.records");
        }

        foreach (
            [
                'attendance_summary_school_select',
                'attendance_summary_school_insert',
                'attendance_summary_school_update',
            ] as $policy
        ) {
            DB::statement("DROP POLICY IF EXISTS {$policy} ON attendance.daily_section_summary");
        }

        DB::statement('ALTER TABLE attendance.sessions NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE attendance.sessions DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE attendance.records NO FORCE ROW LEVEL SECURITY');
        // records remain ENABLE with legacy fail-closed ALL policy on full down of earlier migrations;
        // restore prior ENABLE + ALL policy shape from 2026_09_07.
        DB::statement('ALTER TABLE attendance.daily_section_summary NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE attendance.daily_section_summary DISABLE ROW LEVEL SECURITY');

        DB::statement("
            CREATE POLICY attendance_school_isolation ON attendance.records
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");
    }
};
