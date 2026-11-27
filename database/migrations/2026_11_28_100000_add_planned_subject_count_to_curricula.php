<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «عدد المواد» of a curriculum (owner, 2026-11-28): a new curriculum states how many subjects it should have; with
 * fewer it is accepted but «غير مكتمل», with the share reached (2 of 8 = 25%).
 *
 * curriculum.curricula + planned_subject_count SMALLINT NULL, CHECK 1–200 (NULL = not stated: curricula made before,
 * and API v1 callers). Required by the «المناهج» page.
 */
return new class extends Migration
{
    public function up(): void
    {
        $curricula = SchemaHelper::qualified('curriculum', 'curricula');
        if (! Schema::hasColumn($curricula, 'planned_subject_count')) {
            Schema::table($curricula, function (Blueprint $table): void {
                $table->smallInteger('planned_subject_count')->nullable()->after('section_id');
            });
        }
        if (SchemaHelper::isPostgreSql()) {
            DB::statement("ALTER TABLE {$curricula} DROP CONSTRAINT IF EXISTS curricula_planned_subject_count_check");
            DB::statement("ALTER TABLE {$curricula} ADD CONSTRAINT curricula_planned_subject_count_check CHECK (planned_subject_count IS NULL OR planned_subject_count BETWEEN 1 AND 200)");
        }
    }

    public function down(): void
    {
        $curricula = SchemaHelper::qualified('curriculum', 'curricula');
        if (SchemaHelper::isPostgreSql()) {
            DB::statement("ALTER TABLE {$curricula} DROP CONSTRAINT IF EXISTS curricula_planned_subject_count_check");
        }
        if (Schema::hasColumn($curricula, 'planned_subject_count')) {
            Schema::table($curricula, fn (Blueprint $table) => $table->dropColumn('planned_subject_count'));
        }
    }
};
