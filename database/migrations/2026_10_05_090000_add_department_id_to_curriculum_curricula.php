<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Curricula belong to the branch's الاختصاص (organization.departments) directly.
 * Additive: specialization_id stays (unused by the UI) — reversible.
 * Backfill derives department_id from the old specialization → department link.
 * No new index: matching already narrows by (school_id, academic_year_id, grade_level_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        $curricula = SchemaHelper::qualified('curriculum', 'curricula');

        if (! Schema::hasColumn($curricula, 'department_id')) {
            Schema::table($curricula, function (Blueprint $table): void {
                $table->foreignId('department_id')
                    ->nullable()
                    ->after('specialization_id')
                    ->constrained(SchemaHelper::qualified('organization', 'departments'))
                    ->restrictOnDelete();
            });
        }

        if (SchemaHelper::isPostgreSql()) {
            $specializations = SchemaHelper::qualified('vocational', 'specializations');
            DB::statement("
                UPDATE {$curricula} AS c
                SET department_id = sp.department_id
                FROM {$specializations} AS sp
                WHERE sp.id = c.specialization_id
                  AND c.department_id IS NULL
                  AND sp.department_id IS NOT NULL
            ");
        }
    }

    public function down(): void
    {
        $curricula = SchemaHelper::qualified('curriculum', 'curricula');

        if (Schema::hasColumn($curricula, 'department_id')) {
            Schema::table($curricula, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('department_id');
            });
        }
    }
};
