<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: prior-study subject scores for department-gated admission fields.
 * mathematics_grade — الامن السبراني; physics_grade — الأجهزة الطبية.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission.applications', function (Blueprint $table) {
            $table->decimal('mathematics_grade', 5, 2)->nullable();
            $table->decimal('physics_grade', 5, 2)->nullable();
        });

        DB::statement(
            'ALTER TABLE admission.applications
             ADD CONSTRAINT applications_mathematics_grade_check
             CHECK (mathematics_grade IS NULL OR (mathematics_grade >= 0 AND mathematics_grade <= 100))'
        );
        DB::statement(
            'ALTER TABLE admission.applications
             ADD CONSTRAINT applications_physics_grade_check
             CHECK (physics_grade IS NULL OR (physics_grade >= 0 AND physics_grade <= 100))'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE admission.applications DROP CONSTRAINT IF EXISTS applications_mathematics_grade_check');
        DB::statement('ALTER TABLE admission.applications DROP CONSTRAINT IF EXISTS applications_physics_grade_check');

        Schema::table('admission.applications', function (Blueprint $table) {
            $table->dropColumn(['mathematics_grade', 'physics_grade']);
        });
    }
};
