<?php

use App\Database\SchemaHelper;
use App\Database\StudentGradesPartitionManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3B — authoritative exams.student_grades (LIST partitioned by academic_year_id).
 * No DEFAULT partition. No term_results / annual_results / transcripts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (SchemaHelper::isPostgreSql()) {
            $this->prepareCompositeUniqueSupports();
            $this->createPartitionedStudentGrades();
            StudentGradesPartitionManager::ensurePartitionsForExistingYears();
            $this->addPostgreSqlConstraintsAndIndexes();
            $this->addRejectDeleteTrigger();

            return;
        }

        $this->createSqliteStudentGrades();
    }

    private function prepareCompositeUniqueSupports(): void
    {
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS exam_enrollments_id_school_id_unique
            ON exams.exam_enrollments (id, school_id)
        ');
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS exam_sessions_id_school_id_unique
            ON exams.exam_sessions (id, school_id)
        ');
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS enrollments_id_school_id_unique
            ON enrollment.enrollments (id, school_id)
        ');
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS enrollments_id_academic_year_id_unique
            ON enrollment.enrollments (id, academic_year_id)
        ');
    }

    private function createPartitionedStudentGrades(): void
    {
        DB::statement('
            CREATE TABLE exams.student_grades (
                id BIGINT GENERATED ALWAYS AS IDENTITY,
                academic_year_id BIGINT NOT NULL,
                school_id BIGINT NOT NULL,
                exam_enrollment_id BIGINT NOT NULL,
                exam_session_id BIGINT NOT NULL,
                enrollment_id BIGINT NOT NULL,
                student_id BIGINT NOT NULL,
                subject_id BIGINT NOT NULL,
                score NUMERIC(5,2),
                max_score NUMERIC(5,2) NOT NULL,
                is_absent BOOLEAN NOT NULL DEFAULT false,
                status SMALLINT NOT NULL DEFAULT 1,
                is_current BOOLEAN NOT NULL DEFAULT true,
                correction_of_grade_id BIGINT,
                entered_by BIGINT,
                entered_at TIMESTAMPTZ NOT NULL,
                finalized_at TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id, academic_year_id),
                CONSTRAINT student_grades_academic_year_fk
                    FOREIGN KEY (academic_year_id) REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                CONSTRAINT student_grades_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT student_grades_student_fk
                    FOREIGN KEY (student_id) REFERENCES students.students(id) ON DELETE RESTRICT,
                CONSTRAINT student_grades_subject_fk
                    FOREIGN KEY (subject_id) REFERENCES curriculum.subjects(id) ON DELETE RESTRICT,
                CONSTRAINT student_grades_entered_by_fk
                    FOREIGN KEY (entered_by) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT student_grades_exam_enrollment_school_fk
                    FOREIGN KEY (exam_enrollment_id, school_id)
                    REFERENCES exams.exam_enrollments(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT student_grades_exam_session_school_fk
                    FOREIGN KEY (exam_session_id, school_id)
                    REFERENCES exams.exam_sessions(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT student_grades_enrollment_school_fk
                    FOREIGN KEY (enrollment_id, school_id)
                    REFERENCES enrollment.enrollments(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT student_grades_enrollment_year_fk
                    FOREIGN KEY (enrollment_id, academic_year_id)
                    REFERENCES enrollment.enrollments(id, academic_year_id) ON DELETE RESTRICT,
                CONSTRAINT student_grades_correction_fk
                    FOREIGN KEY (correction_of_grade_id, academic_year_id)
                    REFERENCES exams.student_grades(id, academic_year_id) ON DELETE RESTRICT
            ) PARTITION BY LIST (academic_year_id)
        ');
    }

    private function addPostgreSqlConstraintsAndIndexes(): void
    {
        DB::statement('
            ALTER TABLE exams.student_grades
            ADD CONSTRAINT student_grades_score_absent_check
            CHECK (
                (is_absent = true AND score IS NULL)
                OR (
                    is_absent = false
                    AND score IS NOT NULL
                    AND score >= 0
                    AND score <= max_score
                )
            )
        ');
        DB::statement('
            ALTER TABLE exams.student_grades
            ADD CONSTRAINT student_grades_max_score_check
            CHECK (max_score > 0)
        ');
        DB::statement('
            ALTER TABLE exams.student_grades
            ADD CONSTRAINT student_grades_status_check
            CHECK (status BETWEEN 1 AND 5)
        ');
        DB::statement('
            ALTER TABLE exams.student_grades
            ADD CONSTRAINT student_grades_void_not_current_check
            CHECK (status <> 5 OR is_current = false)
        ');
        DB::statement('
            ALTER TABLE exams.student_grades
            ADD CONSTRAINT student_grades_no_self_correction_check
            CHECK (correction_of_grade_id IS NULL OR correction_of_grade_id <> id)
        ');

        DB::statement('
            CREATE UNIQUE INDEX student_grades_current_enrollment_uidx
            ON exams.student_grades (exam_enrollment_id, academic_year_id)
            WHERE is_current
        ');
        DB::statement('
            CREATE INDEX student_grades_student_year_idx
            ON exams.student_grades (student_id, academic_year_id)
        ');
        DB::statement('
            CREATE INDEX student_grades_enrollment_year_idx
            ON exams.student_grades (enrollment_id, academic_year_id)
        ');
        DB::statement('
            CREATE INDEX student_grades_school_year_subject_idx
            ON exams.student_grades (school_id, academic_year_id, subject_id)
        ');
        DB::statement('
            CREATE INDEX student_grades_session_year_idx
            ON exams.student_grades (exam_session_id, academic_year_id)
        ');
        DB::statement('
            CREATE INDEX student_grades_correction_chain_idx
            ON exams.student_grades (correction_of_grade_id, academic_year_id)
            WHERE correction_of_grade_id IS NOT NULL
        ');
    }

    private function addRejectDeleteTrigger(): void
    {
        DB::statement('
            CREATE OR REPLACE FUNCTION exams.reject_student_grades_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION \'Hard delete of exams.student_grades is forbidden\';
            END;
            $$
        ');
        DB::statement('
            CREATE TRIGGER student_grades_reject_delete
            BEFORE DELETE ON exams.student_grades
            FOR EACH ROW
            EXECUTE FUNCTION exams.reject_student_grades_delete()
        ');
    }

    private function createSqliteStudentGrades(): void
    {
        Schema::create(SchemaHelper::qualified('exams', 'student_grades'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('academic_year_id');
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('exam_enrollment_id');
            $table->unsignedBigInteger('exam_session_id');
            $table->unsignedBigInteger('enrollment_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->decimal('score', 5, 2)->nullable();
            $table->decimal('max_score', 5, 2);
            $table->boolean('is_absent')->default(false);
            $table->smallInteger('status')->default(1);
            $table->boolean('is_current')->default(true);
            $table->unsignedBigInteger('correction_of_grade_id')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('entered_at');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->foreign('academic_year_id')
                ->references('id')
                ->on(SchemaHelper::qualified('academic', 'academic_years'))
                ->restrictOnDelete();
            $table->foreign('school_id')
                ->references('id')
                ->on(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();
            $table->foreign('exam_enrollment_id')
                ->references('id')
                ->on(SchemaHelper::qualified('exams', 'exam_enrollments'))
                ->restrictOnDelete();
            $table->foreign('exam_session_id')
                ->references('id')
                ->on(SchemaHelper::qualified('exams', 'exam_sessions'))
                ->restrictOnDelete();
            $table->foreign('enrollment_id')
                ->references('id')
                ->on(SchemaHelper::qualified('enrollment', 'enrollments'))
                ->restrictOnDelete();
            $table->foreign('student_id')
                ->references('id')
                ->on(SchemaHelper::qualified('students', 'students'))
                ->restrictOnDelete();
            $table->foreign('subject_id')
                ->references('id')
                ->on(SchemaHelper::qualified('curriculum', 'subjects'))
                ->restrictOnDelete();

            $table->index(['student_id', 'academic_year_id']);
            $table->index(['enrollment_id', 'academic_year_id']);
            $table->index(['school_id', 'academic_year_id', 'subject_id']);
            $table->index(['exam_session_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        if (SchemaHelper::isPostgreSql()) {
            DB::statement('DROP TRIGGER IF EXISTS student_grades_reject_delete ON exams.student_grades');
            DB::statement('DROP FUNCTION IF EXISTS exams.reject_student_grades_delete()');
            DB::statement('DROP TABLE IF EXISTS exams.student_grades CASCADE');
            DB::statement('DROP INDEX IF EXISTS exams.exam_enrollments_id_school_id_unique');
            DB::statement('DROP INDEX IF EXISTS enrollment.enrollments_id_academic_year_id_unique');

            return;
        }

        Schema::dropIfExists(SchemaHelper::qualified('exams', 'student_grades'));
    }
};
