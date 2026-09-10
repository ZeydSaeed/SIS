<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3A — Exam foundation only (blueprint exams subset).
 * Creates: exam_types, exams, exam_sessions, exam_enrollments.
 * Does NOT create student_grades / results / transcripts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (SchemaHelper::isPostgreSql()) {
            DB::statement('CREATE SCHEMA IF NOT EXISTS exams');
        }

        // Support composite FK (enrollment_id, school_id) → enrollments(id, school_id).
        if (SchemaHelper::isPostgreSql()) {
            DB::statement('
                CREATE UNIQUE INDEX IF NOT EXISTS enrollments_id_school_id_unique
                ON enrollment.enrollments (id, school_id)
            ');
        } else {
            Schema::table(SchemaHelper::qualified('enrollment', 'enrollments'), function (Blueprint $table) {
                $table->unique(['id', 'school_id'], 'enrollments_id_school_id_unique');
            });
        }

        Schema::create(SchemaHelper::qualified('exams', 'exam_types'), function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->smallInteger('weight_percentage');
        });

        Schema::create(SchemaHelper::qualified('exams', 'exams'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')
                ->constrained(SchemaHelper::qualified('academic', 'academic_years'))
                ->restrictOnDelete();
            $table->foreignId('school_id')
                ->constrained(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();
            $table->foreignId('term_id')
                ->constrained(SchemaHelper::qualified('academic', 'terms'))
                ->restrictOnDelete();
            $table->unsignedSmallInteger('exam_type_id');
            $table->foreign('exam_type_id')
                ->references('id')
                ->on(SchemaHelper::qualified('exams', 'exam_types'))
                ->restrictOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->smallInteger('status')->default(1);
            $table->timestamps();

            $table->index(['academic_year_id', 'school_id']);
            $table->index('status');
            $table->unique(['id', 'school_id'], 'exams_id_school_id_unique');
        });

        Schema::create(SchemaHelper::qualified('exams', 'exam_sessions'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_id');
            $table->unsignedBigInteger('school_id');
            $table->foreignId('subject_id')
                ->constrained(SchemaHelper::qualified('curriculum', 'subjects'))
                ->restrictOnDelete();
            $table->date('session_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('room_id')
                ->nullable()
                ->constrained(SchemaHelper::qualified('organization', 'rooms'))
                ->restrictOnDelete();
            $table->smallInteger('max_grade')->default(100);
            $table->smallInteger('pass_grade')->default(50);
            $table->smallInteger('status')->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['exam_id', 'school_id'], 'exam_sessions_exam_school_fk')
                ->references(['id', 'school_id'])
                ->on(SchemaHelper::qualified('exams', 'exams'))
                ->restrictOnDelete();
            $table->foreign('school_id')
                ->references('id')
                ->on(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();

            $table->index('exam_id');
            $table->index(['subject_id', 'session_date']);
            $table->index('school_id');
            $table->unique(['id', 'school_id'], 'exam_sessions_id_school_id_unique');
        });

        Schema::create(SchemaHelper::qualified('exams', 'exam_enrollments'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_session_id');
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('enrollment_id');
            $table->string('seat_number', 10)->nullable();
            $table->smallInteger('status')->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['exam_session_id', 'school_id'], 'exam_enrollments_session_school_fk')
                ->references(['id', 'school_id'])
                ->on(SchemaHelper::qualified('exams', 'exam_sessions'))
                ->restrictOnDelete();
            $table->foreign(['enrollment_id', 'school_id'], 'exam_enrollments_enrollment_school_fk')
                ->references(['id', 'school_id'])
                ->on(SchemaHelper::qualified('enrollment', 'enrollments'))
                ->restrictOnDelete();
            $table->foreign('school_id')
                ->references('id')
                ->on(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();

            $table->unique(['exam_session_id', 'enrollment_id'], 'exam_enrollments_session_enrollment_unique');
            $table->index('exam_session_id');
            $table->index('enrollment_id');
            $table->index('school_id');
        });

        if (SchemaHelper::isPostgreSql()) {
            $this->addPostgreSqlChecks();
        }
    }

    private function addPostgreSqlChecks(): void
    {
        DB::statement('
            ALTER TABLE exams.exam_types
            ADD CONSTRAINT exam_types_weight_percentage_check
            CHECK (weight_percentage BETWEEN 0 AND 100)
        ');
        DB::statement('
            ALTER TABLE exams.exams
            ADD CONSTRAINT exams_date_range_check
            CHECK (end_date >= start_date)
        ');
        DB::statement('
            ALTER TABLE exams.exams
            ADD CONSTRAINT exams_status_check
            CHECK (status BETWEEN 1 AND 5)
        ');
        DB::statement('
            ALTER TABLE exams.exam_sessions
            ADD CONSTRAINT exam_sessions_time_range_check
            CHECK (end_time > start_time)
        ');
        DB::statement('
            ALTER TABLE exams.exam_sessions
            ADD CONSTRAINT exam_sessions_pass_max_grade_check
            CHECK (pass_grade >= 0 AND max_grade > 0 AND pass_grade <= max_grade)
        ');
        DB::statement('
            ALTER TABLE exams.exam_sessions
            ADD CONSTRAINT exam_sessions_status_check
            CHECK (status BETWEEN 1 AND 4)
        ');
        DB::statement('
            ALTER TABLE exams.exam_enrollments
            ADD CONSTRAINT exam_enrollments_status_check
            CHECK (status BETWEEN 1 AND 5)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists(SchemaHelper::qualified('exams', 'exam_enrollments'));
        Schema::dropIfExists(SchemaHelper::qualified('exams', 'exam_sessions'));
        Schema::dropIfExists(SchemaHelper::qualified('exams', 'exams'));
        Schema::dropIfExists(SchemaHelper::qualified('exams', 'exam_types'));

        if (SchemaHelper::isPostgreSql()) {
            DB::statement('DROP INDEX IF EXISTS enrollment.enrollments_id_school_id_unique');
        } else {
            Schema::table(SchemaHelper::qualified('enrollment', 'enrollments'), function (Blueprint $table) {
                $table->dropUnique('enrollments_id_school_id_unique');
            });
        }
    }
};
