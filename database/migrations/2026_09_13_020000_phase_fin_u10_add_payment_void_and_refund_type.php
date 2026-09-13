<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase FIN-U10 — Soft-void columns on payments + PaymentRefunded ledger type.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            ALTER TABLE finance.payments
            ADD COLUMN IF NOT EXISTS status SMALLINT NOT NULL DEFAULT 1,
            ADD COLUMN IF NOT EXISTS voided_at TIMESTAMPTZ,
            ADD COLUMN IF NOT EXISTS voided_by BIGINT
        ');

        DB::statement('
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = \'finance_payments_status_check\'
                ) THEN
                    ALTER TABLE finance.payments
                    ADD CONSTRAINT finance_payments_status_check
                    CHECK (status IN (1, 2));
                END IF;
            END $$;
        ');

        DB::statement('
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = \'finance_payments_voided_by_fk\'
                ) THEN
                    ALTER TABLE finance.payments
                    ADD CONSTRAINT finance_payments_voided_by_fk
                    FOREIGN KEY (voided_by) REFERENCES public.users(id) ON DELETE SET NULL;
                END IF;
            END $$;
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS finance_payments_student_fee_status_idx
            ON finance.payments (student_fee_id, status)
        ');

        DB::statement('
            ALTER TABLE finance.transactions
            DROP CONSTRAINT IF EXISTS finance_transactions_type_check
        ');
        DB::statement('
            ALTER TABLE finance.transactions
            ADD CONSTRAINT finance_transactions_type_check
            CHECK (transaction_type IN (1, 2, 3))
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            ALTER TABLE finance.transactions
            DROP CONSTRAINT IF EXISTS finance_transactions_type_check
        ');
        DB::statement('
            ALTER TABLE finance.transactions
            ADD CONSTRAINT finance_transactions_type_check
            CHECK (transaction_type IN (1, 2))
        ');

        DB::statement('DROP INDEX IF EXISTS finance.finance_payments_student_fee_status_idx');
        DB::statement('
            ALTER TABLE finance.payments
            DROP CONSTRAINT IF EXISTS finance_payments_voided_by_fk
        ');
        DB::statement('
            ALTER TABLE finance.payments
            DROP CONSTRAINT IF EXISTS finance_payments_status_check
        ');
        DB::statement('
            ALTER TABLE finance.payments
            DROP COLUMN IF EXISTS voided_by,
            DROP COLUMN IF EXISTS voided_at,
            DROP COLUMN IF EXISTS status
        ');
    }
};
