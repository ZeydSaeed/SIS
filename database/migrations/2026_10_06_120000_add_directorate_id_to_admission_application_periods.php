<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An application period belongs to an academic year AND a directorate (المديرية):
 * only the directorate's schools file applications in it.
 *
 * - application_periods.directorate_id: nullable FK → organization.directorates (restrict).
 *   NULL = a legacy shared period (open to every school), kept for existing rows only;
 *   new periods require a directorate (FormRequest + handler).
 * - Backfill: the single directorate of a period's applications' schools; for a period
 *   without applications, the only active directorate when there is exactly one.
 * - No index: periods are listed per academic year (existing year index); the
 *   directorate is a small in-memory filter on that list.
 */
return new class extends Migration
{
    public function up(): void
    {
        $periods = SchemaHelper::qualified('admission', 'application_periods');
        $apps = SchemaHelper::qualified('admission', 'applications');
        $schools = SchemaHelper::qualified('organization', 'schools');
        $directorates = SchemaHelper::qualified('organization', 'directorates');

        if (! Schema::hasColumn($periods, 'directorate_id')) {
            Schema::table($periods, function (Blueprint $table) use ($directorates): void {
                $table->foreignId('directorate_id')
                    ->nullable()
                    ->after('academic_year_id')
                    ->constrained($directorates)
                    ->restrictOnDelete();
            });
        }

        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        // Owner-level backfill: FORCE RLS would hide every row without a school context.
        DB::statement("ALTER TABLE {$periods} NO FORCE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$apps} NO FORCE ROW LEVEL SECURITY");

        DB::statement("
            UPDATE {$periods} AS p
            SET directorate_id = single.directorate_id
            FROM (
                SELECT apps.application_period_id, MIN(s.directorate_id) AS directorate_id
                FROM {$apps} AS apps
                JOIN {$schools} AS s ON s.id = apps.school_id
                GROUP BY apps.application_period_id
                HAVING COUNT(DISTINCT s.directorate_id) = 1
            ) AS single
            WHERE single.application_period_id = p.id
              AND p.directorate_id IS NULL
        ");

        $active = DB::table($directorates)->where('status', 1)->pluck('id');
        if ($active->count() === 1) {
            DB::statement("
                UPDATE {$periods} AS p
                SET directorate_id = ?
                WHERE p.directorate_id IS NULL
                  AND NOT EXISTS (SELECT 1 FROM {$apps} AS apps WHERE apps.application_period_id = p.id)
            ", [(int) $active->first()]);
        }

        DB::statement("ALTER TABLE {$apps} FORCE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$periods} FORCE ROW LEVEL SECURITY");
    }

    public function down(): void
    {
        $periods = SchemaHelper::qualified('admission', 'application_periods');

        if (Schema::hasColumn($periods, 'directorate_id')) {
            Schema::table($periods, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('directorate_id');
            });
        }
    }
};
