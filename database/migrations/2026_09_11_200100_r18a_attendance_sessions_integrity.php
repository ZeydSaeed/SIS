<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * R1.8A — Attendance session integrity hardening.
 *
 * 1) CHECK: status ∈ {1 OPEN, 2 CLOSED, 3 CANCELLED} (vocabulary only).
 * 2) Partial UNIQUE on OPEN sessions for locked natural key with NULLS NOT DISTINCT
 *    on period_id (PostgreSQL 15+; verified live PG 18.2).
 *
 * Does NOT encode lifecycle transitions. Does NOT alter RLS / Strategy A.
 */
return new class extends Migration
{
    private const CHECK_NAME = 'attendance_sessions_status_check';

    private const OPEN_UNIQUE_INDEX = 'attendance_sessions_open_natural_key_uidx';

    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            $this->upSqlite();

            return;
        }

        $invalid = (int) DB::table('attendance.sessions')
            ->whereNotIn('status', [1, 2, 3])
            ->count();
        if ($invalid > 0) {
            throw new \RuntimeException(
                "R1.8A cannot add status CHECK: {$invalid} session(s) have status outside {1,2,3}."
            );
        }

        DB::statement('
            ALTER TABLE attendance.sessions
            DROP CONSTRAINT IF EXISTS '.self::CHECK_NAME.'
        ');
        DB::statement('
            ALTER TABLE attendance.sessions
            ADD CONSTRAINT '.self::CHECK_NAME.'
            CHECK (status IN (1, 2, 3))
        ');

        DB::statement('DROP INDEX IF EXISTS attendance.'.self::OPEN_UNIQUE_INDEX);
        DB::statement('
            CREATE UNIQUE INDEX '.self::OPEN_UNIQUE_INDEX.'
            ON attendance.sessions (
                school_id,
                academic_year_id,
                section_id,
                subject_id,
                session_date,
                period_id
            )
            NULLS NOT DISTINCT
            WHERE status = 1
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            $this->downSqlite();

            return;
        }

        DB::statement('DROP INDEX IF EXISTS attendance.'.self::OPEN_UNIQUE_INDEX);
        DB::statement('
            ALTER TABLE attendance.sessions
            DROP CONSTRAINT IF EXISTS '.self::CHECK_NAME.'
        ');
    }

    /**
     * SQLite parity for local unit/feature suites (approximate NULL bucket via COALESCE).
     */
    private function upSqlite(): void
    {
        $table = SchemaHelper::qualified('attendance', 'sessions');

        DB::statement("
            CREATE TRIGGER IF NOT EXISTS attendance_sessions_status_check_ins
            BEFORE INSERT ON {$table}
            FOR EACH ROW
            WHEN NEW.status NOT IN (1, 2, 3)
            BEGIN
                SELECT RAISE(ABORT, 'attendance.sessions.status must be 1, 2, or 3');
            END
        ");
        DB::statement("
            CREATE TRIGGER IF NOT EXISTS attendance_sessions_status_check_upd
            BEFORE UPDATE ON {$table}
            FOR EACH ROW
            WHEN NEW.status NOT IN (1, 2, 3)
            BEGIN
                SELECT RAISE(ABORT, 'attendance.sessions.status must be 1, 2, or 3');
            END
        ");

        DB::statement('DROP INDEX IF EXISTS '.self::OPEN_UNIQUE_INDEX);
        DB::statement("
            CREATE UNIQUE INDEX ".self::OPEN_UNIQUE_INDEX."
            ON {$table} (
                school_id,
                academic_year_id,
                section_id,
                subject_id,
                session_date,
                COALESCE(period_id, -1)
            )
            WHERE status = 1
        ");
    }

    private function downSqlite(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS attendance_sessions_status_check_ins');
        DB::statement('DROP TRIGGER IF EXISTS attendance_sessions_status_check_upd');
        DB::statement('DROP INDEX IF EXISTS '.self::OPEN_UNIQUE_INDEX);
    }
};
