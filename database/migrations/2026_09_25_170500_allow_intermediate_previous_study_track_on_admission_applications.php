<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Allow previous_study_track = 6 (متوسطة) on admission.applications.
 * Impact: LOW — widen CHECK only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = SchemaHelper::qualified('admission', 'applications');

        if (SchemaHelper::isPostgreSql()) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS admission_applications_previous_study_track_chk");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT admission_applications_previous_study_track_chk CHECK (previous_study_track IS NULL OR previous_study_track IN (1, 2, 3, 4, 5, 6))");
        }
    }

    public function down(): void
    {
        $table = SchemaHelper::qualified('admission', 'applications');

        if (SchemaHelper::isPostgreSql()) {
            DB::statement("UPDATE {$table} SET previous_study_track = NULL WHERE previous_study_track = 6");
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS admission_applications_previous_study_track_chk");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT admission_applications_previous_study_track_chk CHECK (previous_study_track IS NULL OR previous_study_track IN (1, 2, 3, 4, 5))");
        }
    }
};
