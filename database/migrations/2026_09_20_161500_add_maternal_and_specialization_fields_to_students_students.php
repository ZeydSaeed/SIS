<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive nullable civil/academic columns on students.students so admission conversion
 * can persist mother-line names and specialization text.
 * Impact: LOW (nullable columns, no indexes, no FK). See database-blueprint.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = SchemaHelper::qualified('students', 'students');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('mother_name', 100)->nullable();
            $blueprint->string('maternal_father_name', 100)->nullable();
            $blueprint->string('maternal_grandfather_name', 100)->nullable();
            $blueprint->string('specialization_name', 100)->nullable();
        });
    }

    public function down(): void
    {
        $table = SchemaHelper::qualified('students', 'students');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn([
                'mother_name',
                'maternal_father_name',
                'maternal_grandfather_name',
                'specialization_name',
            ]);
        });
    }
};
