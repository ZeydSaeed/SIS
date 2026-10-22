<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * EXAMIS U5 — official grades are immutable in place (DL-019 applied to exams.student_grades).
 *
 * A row-level BEFORE UPDATE trigger on the partitioned parent (cloned by PostgreSQL onto every year partition, existing
 * and future) allows only the transitions the application performs — finalize and void — and rejects every other
 * change, so a correction can only be VOID + INSERT. ENABLE ALWAYS: it also fires under session_replication_role =
 * replica (no supported replication / restore path relies on that; see database-blueprint.md).
 *
 * No table, column, index or data change. Does not protect against TRUNCATE or a privileged DISABLE / DROP TRIGGER
 * (accepted residual risk, D3).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION exams.reject_student_grades_in_place_update()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF ROW(NEW.id, NEW.academic_year_id, NEW.school_id, NEW.exam_enrollment_id, NEW.exam_session_id,
                       NEW.enrollment_id, NEW.student_id, NEW.subject_id, NEW.score, NEW.max_score, NEW.is_absent,
                       NEW.correction_of_grade_id, NEW.entered_by, NEW.entered_at, NEW.created_at)
                   IS DISTINCT FROM
                   ROW(OLD.id, OLD.academic_year_id, OLD.school_id, OLD.exam_enrollment_id, OLD.exam_session_id,
                       OLD.enrollment_id, OLD.student_id, OLD.subject_id, OLD.score, OLD.max_score, OLD.is_absent,
                       OLD.correction_of_grade_id, OLD.entered_by, OLD.entered_at, OLD.created_at)
                THEN
                    RAISE EXCEPTION 'exams.student_grades %: an official grade is immutable in place — correct it through VOID + INSERT', OLD.id
                        USING ERRCODE = 'check_violation';
                END IF;

                IF OLD.status = 5 THEN
                    RAISE EXCEPTION 'exams.student_grades %: a voided grade is terminal', OLD.id
                        USING ERRCODE = 'check_violation';
                END IF;

                IF NEW.status = OLD.status THEN
                    IF NEW.is_current IS DISTINCT FROM OLD.is_current OR NEW.finalized_at IS DISTINCT FROM OLD.finalized_at THEN
                        RAISE EXCEPTION 'exams.student_grades %: without a status transition only updated_at may change', OLD.id
                            USING ERRCODE = 'check_violation';
                    END IF;
                ELSIF NEW.status = 4 THEN
                    IF OLD.status NOT IN (1, 2, 3) OR NOT OLD.is_current OR NOT NEW.is_current
                       OR OLD.finalized_at IS NOT NULL OR NEW.finalized_at IS NULL THEN
                        RAISE EXCEPTION 'exams.student_grades %: only a current draft / entered / submitted grade can be finalized, setting finalized_at once', OLD.id
                            USING ERRCODE = 'check_violation';
                    END IF;
                ELSIF NEW.status = 5 THEN
                    IF NOT OLD.is_current OR NEW.is_current OR NEW.finalized_at IS DISTINCT FROM OLD.finalized_at THEN
                        RAISE EXCEPTION 'exams.student_grades %: only a current grade can be voided, and it stops being current', OLD.id
                            USING ERRCODE = 'check_violation';
                    END IF;
                ELSE
                    RAISE EXCEPTION 'exams.student_grades %: status % -> % is not an allowed transition', OLD.id, OLD.status, NEW.status
                        USING ERRCODE = 'check_violation';
                END IF;

                RETURN NEW;
            END;
            $$
            SQL);

        DB::statement('
            CREATE TRIGGER student_grades_reject_in_place_update
            BEFORE UPDATE ON exams.student_grades
            FOR EACH ROW
            EXECUTE FUNCTION exams.reject_student_grades_in_place_update()
        ');
        DB::statement('ALTER TABLE exams.student_grades ENABLE ALWAYS TRIGGER student_grades_reject_in_place_update');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS student_grades_reject_in_place_update ON exams.student_grades');
        DB::statement('DROP FUNCTION IF EXISTS exams.reject_student_grades_in_place_update()');
    }
};
