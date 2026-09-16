<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = SchemaHelper::qualified('students', 'students');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('father_name', 100)->nullable()->after('middle_name');
            $blueprint->string('grandfather_name', 100)->nullable()->after('father_name');
            $blueprint->string('great_grandfather_name', 100)->nullable()->after('grandfather_name');
            $blueprint->string('guardian_triple_name', 255)->nullable()->after('last_name');
            $blueprint->string('governorate', 100)->nullable()->after('nationality');
            $blueprint->string('neighborhood', 100)->nullable()->after('governorate');
            $blueprint->string('locality', 100)->nullable()->after('neighborhood');
            $blueprint->string('house_number', 50)->nullable()->after('locality');
            $blueprint->string('registration_place', 255)->nullable()->after('house_number');
            $blueprint->smallInteger('religion')->default(1)->after('registration_place');
            $blueprint->date('mawalid_date')->nullable()->after('birth_date');
            $blueprint->string('previous_school_name', 255)->nullable()->after('mawalid_date');
            $blueprint->unsignedBigInteger('transfer_document_number')->nullable()->after('previous_school_name');
            $blueprint->date('transfer_document_date')->nullable()->after('transfer_document_number');
            $blueprint->date('school_start_date')->nullable()->after('transfer_document_date');
            $blueprint->string('admitted_class_name', 100)->nullable()->after('school_start_date');
            $blueprint->text('notes')->nullable()->after('admitted_class_name');
        });

        if (SchemaHelper::isPostgreSql()) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT students_students_religion_check CHECK (religion IN (1, 2, 3))");
        }
    }

    public function down(): void
    {
        $table = SchemaHelper::qualified('students', 'students');

        if (SchemaHelper::isPostgreSql()) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS students_students_religion_check");
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn([
                'father_name',
                'grandfather_name',
                'great_grandfather_name',
                'guardian_triple_name',
                'governorate',
                'neighborhood',
                'locality',
                'house_number',
                'registration_place',
                'religion',
                'mawalid_date',
                'previous_school_name',
                'transfer_document_number',
                'transfer_document_date',
                'school_start_date',
                'admitted_class_name',
                'notes',
            ]);
        });
    }
};
