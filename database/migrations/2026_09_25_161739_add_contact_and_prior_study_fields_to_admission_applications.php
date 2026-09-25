<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add parent occupation, residence contact, and prior-study fields to admission.applications.
 * Impact: LOW (additive nullable columns + CHECKs). See database-blueprint.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = SchemaHelper::qualified('admission', 'applications');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('father_occupation', 100)->nullable();
            $blueprint->string('mother_occupation', 100)->nullable();
            $blueprint->smallInteger('administrative_unit')->nullable();
            $blueprint->string('student_mobile', 20)->nullable();
            $blueprint->string('guardian_mobile', 20)->nullable();
            $blueprint->string('previous_school_name', 255)->nullable();
            $blueprint->smallInteger('graduation_year')->nullable();
            $blueprint->decimal('previous_gpa', 5, 2)->nullable();
            $blueprint->smallInteger('previous_study_track')->nullable();
        });

        if (SchemaHelper::isPostgreSql()) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT admission_applications_administrative_unit_chk CHECK (administrative_unit IS NULL OR administrative_unit IN (1, 2, 3))");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT admission_applications_previous_study_track_chk CHECK (previous_study_track IS NULL OR previous_study_track IN (1, 2, 3, 4, 5))");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT admission_applications_previous_gpa_chk CHECK (previous_gpa IS NULL OR (previous_gpa >= 0 AND previous_gpa <= 100))");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT admission_applications_graduation_year_chk CHECK (graduation_year IS NULL OR (graduation_year >= 1950 AND graduation_year <= 2100))");
        }
    }

    public function down(): void
    {
        $table = SchemaHelper::qualified('admission', 'applications');

        if (SchemaHelper::isPostgreSql()) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS admission_applications_administrative_unit_chk");
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS admission_applications_previous_study_track_chk");
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS admission_applications_previous_gpa_chk");
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS admission_applications_graduation_year_chk");
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn([
                'father_occupation',
                'mother_occupation',
                'administrative_unit',
                'student_mobile',
                'guardian_mobile',
                'previous_school_name',
                'graduation_year',
                'previous_gpa',
                'previous_study_track',
            ]);
        });
    }
};
