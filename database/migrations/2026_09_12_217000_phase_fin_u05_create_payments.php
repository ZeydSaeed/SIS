<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase FIN-U05 — Physicalize finance.payments (+ school_id for RLS).
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
            CREATE TABLE finance.payments (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                student_fee_id BIGINT NOT NULL,
                amount NUMERIC(12,2) NOT NULL,
                payment_method SMALLINT NOT NULL,
                payment_reference VARCHAR(100),
                idempotency_key VARCHAR(100) NOT NULL,
                paid_at TIMESTAMPTZ NOT NULL,
                received_by BIGINT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT finance_payments_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT finance_payments_student_fee_fk
                    FOREIGN KEY (student_fee_id) REFERENCES finance.student_fees(id) ON DELETE RESTRICT,
                CONSTRAINT finance_payments_received_by_fk
                    FOREIGN KEY (received_by) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT finance_payments_amount_check
                    CHECK (amount > 0),
                CONSTRAINT finance_payments_method_check
                    CHECK (payment_method IN (1, 2, 3, 9)),
                CONSTRAINT finance_payments_idempotency_uq
                    UNIQUE (idempotency_key)
            )
        ");

        DB::statement('
            CREATE INDEX finance_payments_school_idx
            ON finance.payments (school_id)
        ');
        DB::statement('
            CREATE INDEX finance_payments_student_fee_idx
            ON finance.payments (student_fee_id)
        ');
        DB::statement('
            CREATE INDEX finance_payments_school_paid_at_idx
            ON finance.payments (school_id, paid_at)
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS finance.payments');
    }
};
