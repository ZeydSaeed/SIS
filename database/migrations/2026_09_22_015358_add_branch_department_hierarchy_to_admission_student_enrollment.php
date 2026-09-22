<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hierarchy: organization.branches → departments → vocational.specializations.
 * Branch placement on admission / student / enrollment (nullable FKs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(SchemaHelper::qualified('vocational', 'specializations'), function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('school_id')
                ->constrained(SchemaHelper::qualified('organization', 'departments'))
                ->restrictOnDelete();
            $table->index('department_id');
        });

        Schema::table(SchemaHelper::qualified('enrollment', 'enrollments'), function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('school_id')
                ->constrained(SchemaHelper::qualified('organization', 'branches'))
                ->restrictOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->after('branch_id')
                ->constrained(SchemaHelper::qualified('organization', 'departments'))
                ->restrictOnDelete();
            $table->index('branch_id');
            $table->index('department_id');
        });

        Schema::table(SchemaHelper::qualified('students', 'students'), function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('school_id')
                ->constrained(SchemaHelper::qualified('organization', 'branches'))
                ->restrictOnDelete();
            $table->index('branch_id');
        });

        Schema::table(SchemaHelper::qualified('admission', 'applications'), function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('target_school_id')
                ->constrained(SchemaHelper::qualified('organization', 'branches'))
                ->restrictOnDelete();
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::table(SchemaHelper::qualified('admission', 'applications'), function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table(SchemaHelper::qualified('students', 'students'), function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table(SchemaHelper::qualified('enrollment', 'enrollments'), function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table(SchemaHelper::qualified('vocational', 'specializations'), function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
