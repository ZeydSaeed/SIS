<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * enrollment.enrollments.specialization_id was dropped (2026_09_29_003500).
 * Graduation denorm triggers still selected that column — recreate functions without it.
 * graduation.*.specialization_id remains on graduation tables as optional denorm (nullable match no longer enforced from enrollment).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement("
            CREATE OR REPLACE FUNCTION graduation.enforce_completion_outcome_denorm()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                enr RECORD;
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    IF NEW.enrollment_id IS DISTINCT FROM OLD.enrollment_id
                        OR NEW.school_id IS DISTINCT FROM OLD.school_id
                        OR NEW.student_id IS DISTINCT FROM OLD.student_id
                        OR NEW.academic_year_id IS DISTINCT FROM OLD.academic_year_id
                        OR NEW.specialization_id IS DISTINCT FROM OLD.specialization_id THEN
                        RAISE EXCEPTION 'Identity columns on graduation.completion_outcomes are immutable';
                    END IF;
                    RETURN NEW;
                END IF;

                SELECT e.school_id, e.student_id, e.academic_year_id
                INTO enr
                FROM enrollment.enrollments e
                WHERE e.id = NEW.enrollment_id;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'Enrollment % not found for completion outcome denorm', NEW.enrollment_id;
                END IF;

                IF NEW.school_id IS DISTINCT FROM enr.school_id
                    OR NEW.student_id IS DISTINCT FROM enr.student_id
                    OR NEW.academic_year_id IS DISTINCT FROM enr.academic_year_id THEN
                    RAISE EXCEPTION 'Denormalized identity on graduation.completion_outcomes must match enrollment';
                END IF;

                RETURN NEW;
            END;
            $$
        ");

        DB::statement("
            CREATE OR REPLACE FUNCTION graduation.enforce_graduation_award_denorm()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                enr RECORD;
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    IF NEW.enrollment_id IS DISTINCT FROM OLD.enrollment_id
                        OR NEW.school_id IS DISTINCT FROM OLD.school_id
                        OR NEW.student_id IS DISTINCT FROM OLD.student_id
                        OR NEW.academic_year_id IS DISTINCT FROM OLD.academic_year_id
                        OR NEW.specialization_id IS DISTINCT FROM OLD.specialization_id THEN
                        RAISE EXCEPTION 'Identity columns on graduation.graduation_awards are immutable';
                    END IF;
                    RETURN NEW;
                END IF;

                SELECT e.school_id, e.student_id, e.academic_year_id
                INTO enr
                FROM enrollment.enrollments e
                WHERE e.id = NEW.enrollment_id;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'Enrollment % not found for graduation award denorm', NEW.enrollment_id;
                END IF;

                IF NEW.school_id IS DISTINCT FROM enr.school_id
                    OR NEW.student_id IS DISTINCT FROM enr.student_id
                    OR NEW.academic_year_id IS DISTINCT FROM enr.academic_year_id THEN
                    RAISE EXCEPTION 'Denormalized identity on graduation.graduation_awards must match enrollment';
                END IF;

                RETURN NEW;
            END;
            $$
        ");
    }

    public function down(): void
    {
        // Cannot restore enrollment-sourced specialization_id match after column drop.
    }
};
