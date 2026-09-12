<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase PT-U01 — Physicalize promotion.rules + promotion.records.
 *
 * Design Lock HD-PT-003: school_id on records for FORCE RLS.
 * HD-PT-002: min_gpa stored, not auto-enforced.
 * HD-PT-004: decision record only — no enrollment mutation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS promotion');

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS enrollments_id_school_id_unique
            ON enrollment.enrollments (id, school_id)
        ');
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS enrollments_id_academic_year_id_unique
            ON enrollment.enrollments (id, academic_year_id)
        ');

        DB::statement("
            CREATE TABLE promotion.rules (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                from_grade_level_id SMALLINT NOT NULL,
                to_grade_level_id SMALLINT NOT NULL,
                min_gpa NUMERIC(4,2),
                min_pass_subjects SMALLINT,
                max_failed_subjects SMALLINT,
                is_active BOOLEAN NOT NULL DEFAULT true,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT promotion_rules_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT promotion_rules_from_grade_fk
                    FOREIGN KEY (from_grade_level_id) REFERENCES academic.grade_levels(id) ON DELETE RESTRICT,
                CONSTRAINT promotion_rules_to_grade_fk
                    FOREIGN KEY (to_grade_level_id) REFERENCES academic.grade_levels(id) ON DELETE RESTRICT,
                CONSTRAINT promotion_rules_grade_direction_check
                    CHECK (from_grade_level_id <> to_grade_level_id),
                CONSTRAINT promotion_rules_min_gpa_check
                    CHECK (min_gpa IS NULL OR min_gpa >= 0),
                CONSTRAINT promotion_rules_min_pass_subjects_check
                    CHECK (min_pass_subjects IS NULL OR min_pass_subjects >= 0),
                CONSTRAINT promotion_rules_max_failed_subjects_check
                    CHECK (max_failed_subjects IS NULL OR max_failed_subjects >= 0)
            )
        ");

        DB::statement('
            CREATE INDEX promotion_rules_school_from_grade_idx
            ON promotion.rules (school_id, from_grade_level_id)
        ');

        DB::statement("
            CREATE TABLE promotion.records (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                enrollment_id BIGINT NOT NULL,
                academic_year_id BIGINT NOT NULL,
                from_grade_level_id SMALLINT NOT NULL,
                to_grade_level_id SMALLINT NOT NULL,
                promotion_status SMALLINT NOT NULL,
                gpa_at_promotion NUMERIC(4,2),
                decided_by BIGINT,
                decided_at TIMESTAMPTZ NOT NULL,
                notes TEXT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT promotion_records_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT promotion_records_academic_year_fk
                    FOREIGN KEY (academic_year_id) REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                CONSTRAINT promotion_records_from_grade_fk
                    FOREIGN KEY (from_grade_level_id) REFERENCES academic.grade_levels(id) ON DELETE RESTRICT,
                CONSTRAINT promotion_records_to_grade_fk
                    FOREIGN KEY (to_grade_level_id) REFERENCES academic.grade_levels(id) ON DELETE RESTRICT,
                CONSTRAINT promotion_records_enrollment_school_fk
                    FOREIGN KEY (enrollment_id, school_id)
                    REFERENCES enrollment.enrollments(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT promotion_records_enrollment_year_fk
                    FOREIGN KEY (enrollment_id, academic_year_id)
                    REFERENCES enrollment.enrollments(id, academic_year_id) ON DELETE RESTRICT,
                CONSTRAINT promotion_records_decided_by_fk
                    FOREIGN KEY (decided_by) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT promotion_records_status_check
                    CHECK (promotion_status BETWEEN 1 AND 3),
                CONSTRAINT promotion_records_gpa_check
                    CHECK (gpa_at_promotion IS NULL OR gpa_at_promotion >= 0),
                CONSTRAINT promotion_records_enrollment_year_unique
                    UNIQUE (enrollment_id, academic_year_id)
            )
        ");

        DB::statement('
            CREATE INDEX promotion_records_enrollment_idx
            ON promotion.records (enrollment_id)
        ');
        DB::statement('
            CREATE INDEX promotion_records_year_status_idx
            ON promotion.records (academic_year_id, promotion_status)
        ');
        DB::statement('
            CREATE INDEX promotion_records_school_year_idx
            ON promotion.records (school_id, academic_year_id)
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS promotion.records');
        DB::statement('DROP TABLE IF EXISTS promotion.rules');
    }
};
