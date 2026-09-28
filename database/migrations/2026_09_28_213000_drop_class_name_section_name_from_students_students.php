<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User-approved: drop students.students.class_name + section_name.
 * Class/section placement remains on enrollment.*; admitted_class_name stays as admission grade level.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = SchemaHelper::qualified('students', 'students');

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            $drop = [];
            if (Schema::hasColumn($table, 'class_name')) {
                $drop[] = 'class_name';
            }
            if (Schema::hasColumn($table, 'section_name')) {
                $drop[] = 'section_name';
            }
            if ($drop !== []) {
                $blueprint->dropColumn($drop);
            }
        });
    }

    public function down(): void
    {
        $table = SchemaHelper::qualified('students', 'students');

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (! Schema::hasColumn($table, 'class_name')) {
                $blueprint->string('class_name', 100)->nullable();
            }
            if (! Schema::hasColumn($table, 'section_name')) {
                $blueprint->string('section_name', 100)->nullable();
            }
        });
    }
};
