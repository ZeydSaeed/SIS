<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(SchemaHelper::qualified('curriculum', 'subjects'), function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->smallInteger('subject_type');
            $table->smallInteger('credit_hours')->nullable();
            $table->smallInteger('max_grade')->default(100);
            $table->smallInteger('pass_grade')->default(50);
            $table->smallInteger('status')->default(1);
            $table->timestamps();

            $table->index('status');
        });

        Schema::create(SchemaHelper::qualified('curriculum', 'curricula'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')
                ->constrained(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();
            $table->foreignId('academic_year_id')
                ->constrained(SchemaHelper::qualified('academic', 'academic_years'))
                ->restrictOnDelete();
            $table->unsignedSmallInteger('grade_level_id');
            $table->foreign('grade_level_id')
                ->references('id')
                ->on(SchemaHelper::qualified('academic', 'grade_levels'))
                ->restrictOnDelete();
            $table->unsignedBigInteger('specialization_id')->nullable();
            $table->string('name');
            $table->smallInteger('status')->default(1);
            $table->timestamps();

            $table->index(['school_id', 'academic_year_id', 'grade_level_id']);
        });

        Schema::create(SchemaHelper::qualified('curriculum', 'curriculum_subjects'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_id')
                ->constrained(SchemaHelper::qualified('curriculum', 'curricula'))
                ->restrictOnDelete();
            $table->foreignId('subject_id')
                ->constrained(SchemaHelper::qualified('curriculum', 'subjects'))
                ->restrictOnDelete();
            $table->smallInteger('weekly_hours')->nullable();
            $table->boolean('is_required')->default(true);
            $table->smallInteger('subject_order')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['curriculum_id', 'subject_id']);
        });

        Schema::create(SchemaHelper::qualified('vocational', 'specializations'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')
                ->constrained(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->text('description')->nullable();
            $table->smallInteger('status')->default(1);
            $table->timestamps();

            $table->unique(['school_id', 'code']);
            $table->index('school_id');
        });

        Schema::create(SchemaHelper::qualified('vocational', 'tracks'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('specialization_id')
                ->constrained(SchemaHelper::qualified('vocational', 'specializations'))
                ->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->smallInteger('status')->default(1);
            $table->timestamps();

            $table->index('specialization_id');
        });

        Schema::create(SchemaHelper::qualified('vocational', 'specialization_subjects'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('specialization_id')
                ->constrained(SchemaHelper::qualified('vocational', 'specializations'))
                ->restrictOnDelete();
            $table->foreignId('subject_id')
                ->constrained(SchemaHelper::qualified('curriculum', 'subjects'))
                ->restrictOnDelete();
            $table->boolean('is_required')->default(true);
            $table->smallInteger('credit_hours')->nullable();

            $table->unique(['specialization_id', 'subject_id']);
        });

        Schema::table(SchemaHelper::qualified('enrollment', 'enrollments'), function (Blueprint $table) {
            $table->foreign('specialization_id')
                ->references('id')
                ->on(SchemaHelper::qualified('vocational', 'specializations'))
                ->restrictOnDelete();
        });

        Schema::table(SchemaHelper::qualified('enrollment', 'enrollment_subjects'), function (Blueprint $table) {
            $table->foreign('subject_id')
                ->references('id')
                ->on(SchemaHelper::qualified('curriculum', 'subjects'))
                ->restrictOnDelete();
        });

        Schema::table(SchemaHelper::qualified('curriculum', 'curricula'), function (Blueprint $table) {
            $table->foreign('specialization_id')
                ->references('id')
                ->on(SchemaHelper::qualified('vocational', 'specializations'))
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table(SchemaHelper::qualified('curriculum', 'curricula'), function (Blueprint $table) {
            $table->dropForeign(['specialization_id']);
        });

        Schema::table(SchemaHelper::qualified('enrollment', 'enrollment_subjects'), function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
        });

        Schema::table(SchemaHelper::qualified('enrollment', 'enrollments'), function (Blueprint $table) {
            $table->dropForeign(['specialization_id']);
        });

        Schema::dropIfExists(SchemaHelper::qualified('vocational', 'specialization_subjects'));
        Schema::dropIfExists(SchemaHelper::qualified('vocational', 'tracks'));
        Schema::dropIfExists(SchemaHelper::qualified('vocational', 'specializations'));
        Schema::dropIfExists(SchemaHelper::qualified('curriculum', 'curriculum_subjects'));
        Schema::dropIfExists(SchemaHelper::qualified('curriculum', 'curricula'));
        Schema::dropIfExists(SchemaHelper::qualified('curriculum', 'subjects'));
    }
};
