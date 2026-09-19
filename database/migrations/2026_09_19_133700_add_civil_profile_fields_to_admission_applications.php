<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expand admission.applications with civil-profile fields aligned to students.students naming.
 * Impact: LOW (additive nullable columns + nullable grade_level_id). See database-blueprint.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = SchemaHelper::qualified('admission', 'applications');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('father_name', 100)->nullable();
            $blueprint->string('grandfather_name', 100)->nullable();
            $blueprint->string('great_grandfather_name', 100)->nullable();
            $blueprint->string('mother_name', 100)->nullable();
            $blueprint->string('maternal_father_name', 100)->nullable();
            $blueprint->string('maternal_grandfather_name', 100)->nullable();
            $blueprint->string('birth_place', 255)->nullable();
            $blueprint->foreignId('target_school_id')
                ->nullable()
                ->constrained(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();
            $blueprint->string('intended_grade_name', 100)->nullable();
            $blueprint->string('department_name', 100)->nullable();
            $blueprint->string('specialization_name', 100)->nullable();
            $blueprint->string('governorate', 100)->nullable();
            $blueprint->string('neighborhood', 100)->nullable();
        });

        if (SchemaHelper::isPostgreSql()) {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN grade_level_id DROP NOT NULL");
        }
    }

    public function down(): void
    {
        $table = SchemaHelper::qualified('admission', 'applications');

        if (SchemaHelper::isPostgreSql()) {
            DB::statement("UPDATE {$table} SET grade_level_id = COALESCE(grade_level_id, 1) WHERE grade_level_id IS NULL");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN grade_level_id SET NOT NULL");
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropConstrainedForeignId('target_school_id');
            $blueprint->dropColumn([
                'father_name',
                'grandfather_name',
                'great_grandfather_name',
                'mother_name',
                'maternal_father_name',
                'maternal_grandfather_name',
                'birth_place',
                'intended_grade_name',
                'department_name',
                'specialization_name',
                'governorate',
                'neighborhood',
            ]);
        });
    }
};
