<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase FIN-U08 — Physicalize finance.transactions (+ school_id; unpartitioned).
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
            CREATE TABLE finance.transactions (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                student_id BIGINT NOT NULL,
                academic_year_id BIGINT NOT NULL,
                transaction_type SMALLINT NOT NULL,
                amount NUMERIC(12,2) NOT NULL,
                balance_after NUMERIC(12,2),
                reference_type VARCHAR(50),
                reference_id BIGINT,
                notes TEXT,
                created_by BIGINT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT finance_transactions_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT finance_transactions_student_fk
                    FOREIGN KEY (student_id) REFERENCES students.students(id) ON DELETE RESTRICT,
                CONSTRAINT finance_transactions_year_fk
                    FOREIGN KEY (academic_year_id) REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                CONSTRAINT finance_transactions_created_by_fk
                    FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT finance_transactions_amount_check
                    CHECK (amount > 0),
                CONSTRAINT finance_transactions_type_check
                    CHECK (transaction_type IN (1, 2))
            )
        ");

        DB::statement('
            CREATE INDEX finance_transactions_school_idx
            ON finance.transactions (school_id)
        ');
        DB::statement('
            CREATE INDEX finance_transactions_student_year_idx
            ON finance.transactions (student_id, academic_year_id)
        ');
        DB::statement('
            CREATE INDEX finance_transactions_school_created_idx
            ON finance.transactions (school_id, created_at)
        ');
        DB::statement('
            CREATE INDEX finance_transactions_reference_idx
            ON finance.transactions (reference_type, reference_id)
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS finance.transactions');
    }
};
