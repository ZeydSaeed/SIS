<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Req 1–3: drop specialization_name & stage_name; add class_name.
 * Req 4:   add admission-parity columns so student record keeps full civil + prior-study data.
 * Types match admission.applications exactly.
 * Impact: MEDIUM (column drop + additive nullable). User-approved destructive drops.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = SchemaHelper::qualified('students', 'students');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn(['specialization_name', 'stage_name']);
        });

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('class_name', 100)->nullable();
            $blueprint->string('father_occupation', 100)->nullable();
            $blueprint->string('mother_occupation', 100)->nullable();
            $blueprint->smallInteger('administrative_unit')->nullable();
            $blueprint->smallInteger('graduation_year')->nullable();
            $blueprint->decimal('previous_gpa', 5, 2)->nullable();
            $blueprint->smallInteger('previous_study_track')->nullable();
            $blueprint->decimal('mathematics_grade', 5, 2)->nullable();
            $blueprint->decimal('physics_grade', 5, 2)->nullable();
        });

        if (SchemaHelper::isPostgreSql()) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT students_administrative_unit_chk CHECK (administrative_unit IS NULL OR administrative_unit IN (1, 2, 3))");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT students_previous_study_track_chk CHECK (previous_study_track IS NULL OR previous_study_track IN (1, 2, 3, 4, 5))");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT students_previous_gpa_chk CHECK (previous_gpa IS NULL OR (previous_gpa >= 0 AND previous_gpa <= 100))");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT students_graduation_year_chk CHECK (graduation_year IS NULL OR (graduation_year >= 1950 AND graduation_year <= 2100))");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT students_mathematics_grade_chk CHECK (mathematics_grade IS NULL OR (mathematics_grade >= 0 AND mathematics_grade <= 100))");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT students_physics_grade_chk CHECK (physics_grade IS NULL OR (physics_grade >= 0 AND physics_grade <= 100))");
        }
    }

    public function down(): void
    {
        $table = SchemaHelper::qualified('students', 'students');

        if (SchemaHelper::isPostgreSql()) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS students_administrative_unit_chk");
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS students_previous_study_track_chk");
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS students_previous_gpa_chk");
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS students_graduation_year_chk");
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS students_mathematics_grade_chk");
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS students_physics_grade_chk");
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn([
                'class_name',
                'father_occupation',
                'mother_occupation',
                'administrative_unit',
                'graduation_year',
                'previous_gpa',
                'previous_study_track',
                'mathematics_grade',
                'physics_grade',
            ]);
        });

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('specialization_name', 100)->nullable();
            $blueprint->string('stage_name', 100)->nullable();
        });
    }
};
