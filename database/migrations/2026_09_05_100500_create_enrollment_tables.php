<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(SchemaHelper::qualified('enrollment', 'classes'), function (Blueprint $table) {
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
            $table->string('code', 20);
            $table->string('name', 100);
            $table->smallInteger('capacity')->nullable();
            $table->smallInteger('status')->default(1);
            $table->timestamps();

            $table->unique(['school_id', 'academic_year_id', 'code']);
            $table->index(['school_id', 'academic_year_id']);
        });

        Schema::create(SchemaHelper::qualified('enrollment', 'sections'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')
                ->constrained(SchemaHelper::qualified('enrollment', 'classes'))
                ->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->smallInteger('capacity')->nullable();
            $table->unsignedBigInteger('homeroom_teacher_id')->nullable();
            $table->smallInteger('status')->default(1);
            $table->timestamps();

            $table->unique(['class_id', 'code']);
            $table->index('class_id');
        });

        Schema::create(SchemaHelper::qualified('enrollment', 'enrollments'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained(SchemaHelper::qualified('students', 'students'))
                ->restrictOnDelete();
            $table->foreignId('academic_year_id')
                ->constrained(SchemaHelper::qualified('academic', 'academic_years'))
                ->restrictOnDelete();
            $table->foreignId('school_id')
                ->constrained(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();
            $table->foreignId('class_id')
                ->constrained(SchemaHelper::qualified('enrollment', 'classes'))
                ->restrictOnDelete();
            $table->foreignId('section_id')
                ->constrained(SchemaHelper::qualified('enrollment', 'sections'))
                ->restrictOnDelete();
            $table->unsignedBigInteger('specialization_id')->nullable();
            $table->string('enrollment_number', 50)->unique();
            $table->smallInteger('status')->default(1);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('student_id');
            $table->index(['school_id', 'academic_year_id']);
            $table->index(['student_id', 'academic_year_id']);
        });

        Schema::create(SchemaHelper::qualified('enrollment', 'enrollment_subjects'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')
                ->constrained(SchemaHelper::qualified('enrollment', 'enrollments'))
                ->restrictOnDelete();
            $table->unsignedBigInteger('subject_id');
            $table->boolean('is_elective')->default(false);
            $table->smallInteger('status')->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['enrollment_id', 'subject_id']);
            $table->index('enrollment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SchemaHelper::qualified('enrollment', 'enrollment_subjects'));
        Schema::dropIfExists(SchemaHelper::qualified('enrollment', 'enrollments'));
        Schema::dropIfExists(SchemaHelper::qualified('enrollment', 'sections'));
        Schema::dropIfExists(SchemaHelper::qualified('enrollment', 'classes'));
    }
};
