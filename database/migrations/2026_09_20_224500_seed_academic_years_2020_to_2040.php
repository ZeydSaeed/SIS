<?php

use App\Database\SchemaHelper;
use App\Database\StudentGradesPartitionManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Additive catalog of academic years 2020-2021 … 2040-2041 for admission period selection.
 * Existing current-year flags are left unchanged. Down() is a no-op so periods/students
 * that already reference a seeded year are not orphaned.
 * Impact: LOW (insert-if-missing rows + partition ensure). See database-blueprint.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = SchemaHelper::qualified('academic', 'academic_years');
        $now = now();

        for ($startYear = 2020; $startYear <= 2040; $startYear++) {
            $endYear = $startYear + 1;
            $code = $startYear.'-'.$endYear;
            $existingId = DB::table($table)->where('code', $code)->value('id');

            if ($existingId !== null) {
                StudentGradesPartitionManager::ensurePartitionForAcademicYear((int) $existingId);

                continue;
            }

            $id = (int) DB::table($table)->insertGetId([
                'code' => $code,
                'name' => 'السنة الدراسية '.$code,
                'start_date' => sprintf('%d-09-01', $startYear),
                'end_date' => sprintf('%d-06-30', $endYear),
                'is_current' => false,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            StudentGradesPartitionManager::ensurePartitionForAcademicYear($id);
        }
    }

    public function down(): void
    {
        // Catalog years remain; periods and students may already reference them.
    }
};
