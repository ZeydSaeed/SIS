<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One *active* enrollment per student/year: status=1 AND effective_to IS NULL.
 * Allows historical superseded segments for the same year.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        $table = SchemaHelper::qualified('enrollment', 'enrollments');

        DB::statement('DROP INDEX IF EXISTS enrollment.enrollments_student_year_active_unique');
        DB::statement(
            "CREATE UNIQUE INDEX enrollments_student_year_active_unique
             ON {$table} (student_id, academic_year_id)
             WHERE status = 1 AND effective_to IS NULL",
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS enrollment.enrollments_student_year_active_unique');
    }
};
