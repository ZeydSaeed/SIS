<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase FIN-U03 — Physicalize finance.student_fees (+ school_id for RLS).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS finance');

        DB::statement("
            CREATE TABLE finance.student_fees (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                enrollment_id BIGINT NOT NULL,
                fee_type_id BIGINT NOT NULL,
                academic_year_id BIGINT NOT NULL,
                amount NUMERIC(12,2) NOT NULL,
                due_date DATE,
                status SMALLINT NOT NULL DEFAULT 1,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT finance_student_fees_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT finance_student_fees_enrollment_fk
                    FOREIGN KEY (enrollment_id) REFERENCES enrollment.enrollments(id) ON DELETE RESTRICT,
                CONSTRAINT finance_student_fees_fee_type_fk
                    FOREIGN KEY (fee_type_id) REFERENCES finance.fee_types(id) ON DELETE RESTRICT,
                CONSTRAINT finance_student_fees_year_fk
                    FOREIGN KEY (academic_year_id) REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                CONSTRAINT finance_student_fees_amount_check
                    CHECK (amount >= 0),
                CONSTRAINT finance_student_fees_status_check
                    CHECK (status BETWEEN 1 AND 9),
                CONSTRAINT finance_student_fees_unique_assign
                    UNIQUE (school_id, enrollment_id, fee_type_id, academic_year_id)
            )
        ");

        DB::statement('
            CREATE INDEX finance_student_fees_school_idx
            ON finance.student_fees (school_id)
        ');
        DB::statement('
            CREATE INDEX finance_student_fees_enrollment_idx
            ON finance.student_fees (enrollment_id)
        ');
        DB::statement('
            CREATE INDEX finance_student_fees_year_status_idx
            ON finance.student_fees (academic_year_id, status)
        ');
        DB::statement('
            CREATE INDEX finance_student_fees_school_year_idx
            ON finance.student_fees (school_id, academic_year_id)
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS finance.student_fees');
    }
};
