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
        Schema::create(SchemaHelper::qualified('timetable', 'periods'), function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->foreignId('school_id')
                ->constrained(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();
            $table->smallInteger('period_number');
            $table->time('start_time');
            $table->time('end_time');
            $table->smallInteger('period_type')->default(1);

            $table->unique(['school_id', 'period_number']);
        });

        Schema::create(SchemaHelper::qualified('attendance', 'sessions'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')
                ->constrained(SchemaHelper::qualified('enrollment', 'sections'))
                ->restrictOnDelete();
            $table->foreignId('subject_id')
                ->constrained(SchemaHelper::qualified('curriculum', 'subjects'))
                ->restrictOnDelete();
            $table->foreignId('academic_year_id')
                ->constrained(SchemaHelper::qualified('academic', 'academic_years'))
                ->restrictOnDelete();
            $table->date('session_date');
            $table->unsignedSmallInteger('period_id')->nullable();
            $table->foreignId('teacher_id')
                ->constrained(SchemaHelper::qualified('teachers', 'teachers'))
                ->restrictOnDelete();
            $table->smallInteger('status')->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('period_id')
                ->references('id')
                ->on(SchemaHelper::qualified('timetable', 'periods'))
                ->nullOnDelete();
            $table->index(['section_id', 'session_date']);
            $table->index(['academic_year_id', 'session_date']);
        });

        if (SchemaHelper::isPostgreSql()) {
            $this->createPartitionedRecordsTable();
        } else {
            $this->createStandardRecordsTable();
        }

        Schema::create(SchemaHelper::qualified('attendance', 'daily_section_summary'), function (Blueprint $table) {
            $table->foreignId('section_id')
                ->constrained(SchemaHelper::qualified('enrollment', 'sections'))
                ->restrictOnDelete();
            $table->foreignId('school_id')
                ->constrained(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();
            $table->foreignId('academic_year_id')
                ->constrained(SchemaHelper::qualified('academic', 'academic_years'))
                ->restrictOnDelete();
            $table->date('attendance_date');
            $table->smallInteger('total_students')->default(0);
            $table->smallInteger('present_count')->default(0);
            $table->smallInteger('absent_count')->default(0);
            $table->smallInteger('late_count')->default(0);
            $table->timestamp('updated_at')->useCurrent();

            $table->primary(['section_id', 'attendance_date']);
            $table->index(['school_id', 'attendance_date']);
            $table->index(['academic_year_id', 'attendance_date']);
        });
    }

    private function createStandardRecordsTable(): void
    {
        Schema::create(SchemaHelper::qualified('attendance', 'records'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                ->constrained(SchemaHelper::qualified('attendance', 'sessions'))
                ->restrictOnDelete();
            $table->foreignId('student_id')
                ->constrained(SchemaHelper::qualified('students', 'students'))
                ->restrictOnDelete();
            $table->foreignId('enrollment_id')
                ->constrained(SchemaHelper::qualified('enrollment', 'enrollments'))
                ->restrictOnDelete();
            $table->foreignId('academic_year_id')
                ->constrained(SchemaHelper::qualified('academic', 'academic_years'))
                ->restrictOnDelete();
            $table->foreignId('school_id')
                ->constrained(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();
            $table->date('attendance_date');
            $table->smallInteger('status');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['session_id', 'student_id']);
            $table->index(['student_id', 'attendance_date']);
            $table->index(['academic_year_id', 'attendance_date']);
            $table->index(['school_id', 'attendance_date']);
        });
    }

    private function createPartitionedRecordsTable(): void
    {
        DB::statement('
            CREATE TABLE attendance.records (
                id BIGINT GENERATED ALWAYS AS IDENTITY,
                session_id BIGINT NOT NULL REFERENCES attendance.sessions(id) ON DELETE RESTRICT,
                student_id BIGINT NOT NULL REFERENCES students.students(id) ON DELETE RESTRICT,
                enrollment_id BIGINT NOT NULL REFERENCES enrollment.enrollments(id) ON DELETE RESTRICT,
                academic_year_id BIGINT NOT NULL REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                school_id BIGINT NOT NULL REFERENCES organization.schools(id) ON DELETE RESTRICT,
                attendance_date DATE NOT NULL,
                status SMALLINT NOT NULL,
                notes TEXT,
                recorded_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id, academic_year_id)
            ) PARTITION BY LIST (academic_year_id)
        ');

        DB::statement('
            CREATE TABLE attendance.records_default
            PARTITION OF attendance.records DEFAULT
        ');

        DB::statement('CREATE INDEX attendance_records_student_date_idx ON attendance.records (student_id, attendance_date)');
        DB::statement('CREATE UNIQUE INDEX attendance_records_session_student_unique ON attendance.records (session_id, student_id, academic_year_id)');
        DB::statement('CREATE INDEX attendance_records_year_date_idx ON attendance.records (academic_year_id, attendance_date)');
        DB::statement('CREATE INDEX attendance_records_school_date_idx ON attendance.records (school_id, attendance_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists(SchemaHelper::qualified('attendance', 'daily_section_summary'));
        Schema::dropIfExists(SchemaHelper::qualified('attendance', 'records'));
        Schema::dropIfExists(SchemaHelper::qualified('attendance', 'sessions'));
        Schema::dropIfExists(SchemaHelper::qualified('timetable', 'periods'));
    }
};
