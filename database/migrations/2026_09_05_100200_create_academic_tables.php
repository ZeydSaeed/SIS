<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(SchemaHelper::qualified('academic', 'academic_years'), function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->smallInteger('status')->default(1);
            $table->timestamps();

            $table->index('is_current');
        });

        Schema::create(SchemaHelper::qualified('academic', 'terms'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')
                ->constrained(SchemaHelper::qualified('academic', 'academic_years'))
                ->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->smallInteger('term_order');
            $table->smallInteger('status')->default(1);
            $table->timestamps();

            $table->unique(['academic_year_id', 'code']);
            $table->index('academic_year_id');
        });

        Schema::create(SchemaHelper::qualified('academic', 'grade_levels'), function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->smallInteger('level_order');
            $table->smallInteger('education_stage');
            $table->smallInteger('status')->default(1);

            $table->index('level_order');
        });

        Schema::create(SchemaHelper::qualified('academic', 'holidays'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')
                ->constrained(SchemaHelper::qualified('academic', 'academic_years'))
                ->restrictOnDelete();
            $table->foreignId('school_id')
                ->nullable()
                ->constrained(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->smallInteger('holiday_type');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['academic_year_id', 'start_date']);
            $table->index(['school_id', 'start_date']);
        });

        Schema::create(SchemaHelper::qualified('academic', 'system_settings'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')
                ->nullable()
                ->constrained(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();
            $table->string('setting_key', 100);
            $table->json('setting_value');
            $table->text('description')->nullable();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['school_id', 'setting_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SchemaHelper::qualified('academic', 'system_settings'));
        Schema::dropIfExists(SchemaHelper::qualified('academic', 'holidays'));
        Schema::dropIfExists(SchemaHelper::qualified('academic', 'grade_levels'));
        Schema::dropIfExists(SchemaHelper::qualified('academic', 'terms'));
        Schema::dropIfExists(SchemaHelper::qualified('academic', 'academic_years'));
    }
};
