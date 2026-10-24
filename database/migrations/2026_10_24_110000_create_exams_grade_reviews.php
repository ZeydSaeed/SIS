<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * EXAMIS B — exams.grade_reviews: an objection to / review of one official grade. A review never edits the grade:
 * accepting it goes through the existing correction (VOID + INSERT) and records the resulting grade. Rows are never
 * deleted and a decided review is final. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement("
            CREATE TABLE exams.grade_reviews (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                review_number VARCHAR(32) GENERATED ALWAYS AS ('GR-' || lpad(id::text, 6, '0')) STORED,
                school_id BIGINT NOT NULL,
                academic_year_id BIGINT NOT NULL,
                grade_id BIGINT NOT NULL,
                score_at_request NUMERIC(8,2),
                absent_at_request BOOLEAN NOT NULL,
                reason TEXT NOT NULL,
                requested_by BIGINT,
                requested_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                status VARCHAR(16) NOT NULL DEFAULT 'submitted',
                assigned_to BIGINT,
                started_at TIMESTAMPTZ,
                decided_by BIGINT,
                decided_at TIMESTAMPTZ,
                decision_note TEXT,
                resulting_grade_id BIGINT,
                correlation_id VARCHAR(64),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT grade_reviews_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT grade_reviews_grade_fk
                    FOREIGN KEY (grade_id, academic_year_id) REFERENCES exams.student_grades(id, academic_year_id) ON DELETE RESTRICT,
                CONSTRAINT grade_reviews_resulting_grade_fk
                    FOREIGN KEY (resulting_grade_id, academic_year_id) REFERENCES exams.student_grades(id, academic_year_id) ON DELETE RESTRICT,
                CONSTRAINT grade_reviews_requested_by_fk
                    FOREIGN KEY (requested_by) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT grade_reviews_assigned_to_fk
                    FOREIGN KEY (assigned_to) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT grade_reviews_decided_by_fk
                    FOREIGN KEY (decided_by) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT grade_reviews_status_check
                    CHECK (status IN ('submitted', 'under_review', 'accepted', 'rejected', 'withdrawn')),
                CONSTRAINT grade_reviews_reason_check CHECK (length(btrim(reason)) > 0),
                CONSTRAINT grade_reviews_under_review_check
                    CHECK (status <> 'under_review' OR (assigned_to IS NOT NULL AND started_at IS NOT NULL)),
                CONSTRAINT grade_reviews_decision_check CHECK (
                    (status IN ('accepted', 'rejected') AND decided_at IS NOT NULL AND decided_by IS NOT NULL AND length(btrim(decision_note)) > 0)
                    OR (status NOT IN ('accepted', 'rejected') AND decided_at IS NULL)
                ),
                CONSTRAINT grade_reviews_accepted_check
                    CHECK ((status = 'accepted') = (resulting_grade_id IS NOT NULL))
            )
        ");

        DB::statement("
            CREATE UNIQUE INDEX grade_reviews_one_open_per_grade_unique
            ON exams.grade_reviews (school_id, grade_id)
            WHERE status IN ('submitted', 'under_review')
        ");
        DB::statement('CREATE UNIQUE INDEX grade_reviews_number_unique ON exams.grade_reviews (school_id, review_number)');
        DB::statement('CREATE INDEX grade_reviews_school_year_status_idx ON exams.grade_reviews (school_id, academic_year_id, status)');

        DB::statement('
            CREATE FUNCTION exams.grade_reviews_guard() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = \'DELETE\' THEN
                    RAISE EXCEPTION \'exams.grade_reviews rows are never deleted\';
                END IF;
                IF OLD.status IN (\'accepted\', \'rejected\', \'withdrawn\') THEN
                    RAISE EXCEPTION \'a decided grade review is final\';
                END IF;
                IF NEW.school_id <> OLD.school_id OR NEW.academic_year_id <> OLD.academic_year_id
                    OR NEW.grade_id <> OLD.grade_id OR NEW.reason <> OLD.reason
                    OR NEW.requested_by IS DISTINCT FROM OLD.requested_by OR NEW.requested_at <> OLD.requested_at
                    OR NEW.score_at_request IS DISTINCT FROM OLD.score_at_request
                    OR NEW.absent_at_request <> OLD.absent_at_request THEN
                    RAISE EXCEPTION \'the request of a grade review is immutable\';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        ');
        DB::statement('
            CREATE TRIGGER grade_reviews_guard
            BEFORE UPDATE OR DELETE ON exams.grade_reviews
            FOR EACH ROW EXECUTE FUNCTION exams.grade_reviews_guard()
        ');
        DB::statement('ALTER TABLE exams.grade_reviews ENABLE ALWAYS TRIGGER grade_reviews_guard');

        DB::statement('ALTER TABLE exams.grade_reviews ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE exams.grade_reviews FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY grade_reviews_school_isolation ON exams.grade_reviews
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS exams.grade_reviews');
        DB::statement('DROP FUNCTION IF EXISTS exams.grade_reviews_guard()');
    }
};
