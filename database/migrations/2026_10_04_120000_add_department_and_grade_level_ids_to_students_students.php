<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow A1 — structured placement on the student record.
 * Additive only: department_name / admitted_class_name text stay as the admission
 * snapshot; the new nullable FKs are kept in sync by the student repository and
 * backfilled per school by `sis:backfill-student-placement-ids` (RLS-safe).
 * No index: no query filters or joins on these columns yet (indexing governance).
 */
return new class extends Migration
{
    public function up(): void
    {
        $students = SchemaHelper::qualified('students', 'students');

        Schema::table($students, function (Blueprint $table) use ($students): void {
            if (! Schema::hasColumn($students, 'department_id')) {
                $table->foreignId('department_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained(SchemaHelper::qualified('organization', 'departments'))
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn($students, 'grade_level_id')) {
                $table->unsignedSmallInteger('grade_level_id')->nullable()->after('admitted_class_name');
                $table->foreign('grade_level_id')
                    ->references('id')
                    ->on(SchemaHelper::qualified('academic', 'grade_levels'))
                    ->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        $students = SchemaHelper::qualified('students', 'students');

        Schema::table($students, function (Blueprint $table) use ($students): void {
            if (Schema::hasColumn($students, 'grade_level_id')) {
                $table->dropForeign(['grade_level_id']);
                $table->dropColumn('grade_level_id');
            }

            if (Schema::hasColumn($students, 'department_id')) {
                $table->dropConstrainedForeignId('department_id');
            }
        });
    }
};
