<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = SchemaHelper::qualified('students', 'students');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('mobile', 30)->nullable()->after('guardian_triple_name');
            $blueprint->string('guardian_mobile', 30)->nullable()->after('mobile');
            $blueprint->string('email', 255)->nullable()->after('guardian_mobile');
            $blueprint->string('school_name', 255)->nullable()->after('email');
            $blueprint->string('department_name', 100)->nullable()->after('school_name');
            $blueprint->string('stage_name', 100)->nullable()->after('department_name');
            $blueprint->string('section_name', 100)->nullable()->after('stage_name');
        });
    }

    public function down(): void
    {
        $table = SchemaHelper::qualified('students', 'students');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn([
                'mobile',
                'guardian_mobile',
                'email',
                'school_name',
                'department_name',
                'stage_name',
                'section_name',
            ]);
        });
    }
};
