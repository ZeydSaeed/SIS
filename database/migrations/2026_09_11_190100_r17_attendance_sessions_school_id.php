<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R1.7 Strategy A — denormalized attendance.sessions.school_id
 * (historical ownership snapshot for direct RLS; stamped at session create).
 */
return new class extends Migration
{
    public function up(): void
    {
        $sessions = SchemaHelper::qualified('attendance', 'sessions');
        $schools = SchemaHelper::qualified('organization', 'schools');

        if (! Schema::hasColumn($sessions, 'school_id')) {
            Schema::table($sessions, function (Blueprint $table) use ($schools): void {
                // foreignId creates BTREE on school_id — required for RLS predicate.
                $table->foreignId('school_id')
                    ->nullable()
                    ->constrained($schools)
                    ->restrictOnDelete();
            });
        }

        if (SchemaHelper::isPostgreSql()) {
            DB::statement("
                UPDATE attendance.sessions AS sess
                SET school_id = cls.school_id
                FROM enrollment.sections AS sec
                JOIN enrollment.classes AS cls ON cls.id = sec.class_id
                WHERE sess.section_id = sec.id
                  AND sess.school_id IS NULL
            ");
        } else {
            $rows = DB::table("{$sessions} as sess")
                ->join(SchemaHelper::qualified('enrollment', 'sections').' as sec', 'sec.id', '=', 'sess.section_id')
                ->join(SchemaHelper::qualified('enrollment', 'classes').' as cls', 'cls.id', '=', 'sec.class_id')
                ->whereNull('sess.school_id')
                ->select(['sess.id', 'cls.school_id'])
                ->get();

            foreach ($rows as $row) {
                DB::table($sessions)->where('id', $row->id)->update(['school_id' => $row->school_id]);
            }
        }

        $orphans = (int) DB::table($sessions)->whereNull('school_id')->count();
        if ($orphans > 0) {
            throw new \RuntimeException(
                "R1.7 cannot set attendance.sessions.school_id NOT NULL: {$orphans} orphan session(s) lack section→class school."
            );
        }

        if (SchemaHelper::isPostgreSql()) {
            DB::statement('ALTER TABLE attendance.sessions ALTER COLUMN school_id SET NOT NULL');
        } else {
            // SQLite: rebuild column as NOT NULL via temporary table pattern is heavy;
            // application + tests always stamp school_id; enforce via CHECK.
            DB::statement("
                CREATE TRIGGER IF NOT EXISTS attendance_sessions_school_id_required
                BEFORE INSERT ON {$sessions}
                FOR EACH ROW
                WHEN NEW.school_id IS NULL
                BEGIN
                    SELECT RAISE(ABORT, 'attendance.sessions.school_id is required');
                END
            ");
            DB::statement("
                CREATE TRIGGER IF NOT EXISTS attendance_sessions_school_id_required_upd
                BEFORE UPDATE ON {$sessions}
                FOR EACH ROW
                WHEN NEW.school_id IS NULL
                BEGIN
                    SELECT RAISE(ABORT, 'attendance.sessions.school_id is required');
                END
            ");
        }
    }

    public function down(): void
    {
        $sessions = SchemaHelper::qualified('attendance', 'sessions');

        if (! SchemaHelper::isPostgreSql()) {
            DB::statement("DROP TRIGGER IF EXISTS attendance_sessions_school_id_required");
            DB::statement("DROP TRIGGER IF EXISTS attendance_sessions_school_id_required_upd");
        }

        if (Schema::hasColumn($sessions, 'school_id')) {
            Schema::table($sessions, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('school_id');
            });
        }
    }
};
