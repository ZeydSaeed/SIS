<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(SchemaHelper::qualified('teachers', 'teachers'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_code', 50)->unique();
            $table->string('national_id', 20)->nullable()->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('full_name');
            $table->string('specialization_field')->nullable();
            $table->date('hire_date')->nullable();
            $table->smallInteger('status')->default(1);
            $table->timestamps();

            $table->index('status');
        });

        Schema::table(SchemaHelper::qualified('enrollment', 'sections'), function (Blueprint $table) {
            $table->foreign('homeroom_teacher_id')
                ->references('id')
                ->on(SchemaHelper::qualified('teachers', 'teachers'))
                ->nullOnDelete();
        });

        Schema::create(SchemaHelper::qualified('teachers', 'teacher_schools'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')
                ->constrained(SchemaHelper::qualified('teachers', 'teachers'))
                ->restrictOnDelete();
            $table->foreignId('school_id')
                ->constrained(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();
            $table->foreignId('academic_year_id')
                ->constrained(SchemaHelper::qualified('academic', 'academic_years'))
                ->restrictOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['teacher_id', 'academic_year_id']);
            $table->index(['school_id', 'academic_year_id']);
        });

        Schema::create(SchemaHelper::qualified('teachers', 'teacher_subjects'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')
                ->constrained(SchemaHelper::qualified('teachers', 'teachers'))
                ->restrictOnDelete();
            $table->foreignId('subject_id')
                ->constrained(SchemaHelper::qualified('curriculum', 'subjects'))
                ->restrictOnDelete();
            $table->foreignId('academic_year_id')
                ->constrained(SchemaHelper::qualified('academic', 'academic_years'))
                ->restrictOnDelete();
            $table->foreignId('school_id')
                ->constrained(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['teacher_id', 'subject_id', 'academic_year_id', 'school_id'], 'teacher_subject_year_school_unique');
            $table->index(['teacher_id', 'academic_year_id']);
        });

        Schema::create(SchemaHelper::qualified('teachers', 'teacher_qualifications'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')
                ->constrained(SchemaHelper::qualified('teachers', 'teachers'))
                ->restrictOnDelete();
            $table->smallInteger('qualification_type');
            $table->string('title');
            $table->string('institution')->nullable();
            $table->smallInteger('year_obtained')->nullable();
            $table->string('document_storage_key', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('teacher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SchemaHelper::qualified('teachers', 'teacher_qualifications'));
        Schema::dropIfExists(SchemaHelper::qualified('teachers', 'teacher_subjects'));
        Schema::dropIfExists(SchemaHelper::qualified('teachers', 'teacher_schools'));

        Schema::table(SchemaHelper::qualified('enrollment', 'sections'), function (Blueprint $table) {
            $table->dropForeign(['homeroom_teacher_id']);
        });

        Schema::dropIfExists(SchemaHelper::qualified('teachers', 'teachers'));
    }
};
