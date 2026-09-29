<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop unused enrollment display columns:
 * - specialization_id (vocational specialization; UI uses department_id as الاختصاص)
 * - enrollment_number (removed from enrollment sheet UI)
 *
 * Stage / grade level were never stored on enrollment.enrollments (join-only via class → grade_levels).
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = SchemaHelper::qualified('enrollment', 'enrollments');

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (Schema::hasColumn($table, 'specialization_id')) {
                $fk = $this->foreignKeyName($table, 'specialization_id');
                if ($fk !== null) {
                    $blueprint->dropForeign($fk);
                }
                $blueprint->dropColumn('specialization_id');
            }
        });

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (Schema::hasColumn($table, 'enrollment_number')) {
                $blueprint->dropUnique(['enrollment_number']);
                $blueprint->dropColumn('enrollment_number');
            }
        });
    }

    public function down(): void
    {
        $table = SchemaHelper::qualified('enrollment', 'enrollments');

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (! Schema::hasColumn($table, 'enrollment_number')) {
                $blueprint->string('enrollment_number', 50)->nullable();
            }
            if (! Schema::hasColumn($table, 'specialization_id')) {
                $blueprint->unsignedBigInteger('specialization_id')->nullable();
            }
        });

        // Backfill unique enrollment_number for rollback safety.
        if (Schema::hasColumn($table, 'enrollment_number')) {
            DB::table($table)
                ->whereNull('enrollment_number')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table): void {
                    foreach ($rows as $row) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update(['enrollment_number' => 'ENR-'.$row->id]);
                    }
                });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('enrollment_number', 50)->nullable(false)->change();
                $blueprint->unique('enrollment_number');
            });
        }
    }

    private function foreignKeyName(string $table, string $column): ?string
    {
        $schema = 'enrollment';
        $bareTable = 'enrollments';

        $row = DB::selectOne(
            'SELECT tc.constraint_name
             FROM information_schema.table_constraints AS tc
             JOIN information_schema.key_column_usage AS kcu
               ON tc.constraint_name = kcu.constraint_name
              AND tc.table_schema = kcu.table_schema
             WHERE tc.constraint_type = ?
               AND tc.table_schema = ?
               AND tc.table_name = ?
               AND kcu.column_name = ?
             LIMIT 1',
            ['FOREIGN KEY', $schema, $bareTable, $column],
        );

        return is_object($row) && isset($row->constraint_name)
            ? (string) $row->constraint_name
            : null;
    }
};
