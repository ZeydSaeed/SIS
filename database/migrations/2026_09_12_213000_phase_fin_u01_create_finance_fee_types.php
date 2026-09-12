<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase FIN-U01 — Physicalize finance.fee_types (catalog only).
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
            CREATE TABLE finance.fee_types (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                code VARCHAR(20) NOT NULL,
                name VARCHAR(255) NOT NULL,
                amount NUMERIC(12,2) NOT NULL,
                is_recurring BOOLEAN NOT NULL DEFAULT false,
                status SMALLINT NOT NULL DEFAULT 1,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT finance_fee_types_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT finance_fee_types_amount_check
                    CHECK (amount >= 0),
                CONSTRAINT finance_fee_types_status_check
                    CHECK (status BETWEEN 1 AND 9),
                CONSTRAINT finance_fee_types_school_code_uq
                    UNIQUE (school_id, code)
            )
        ");

        DB::statement('
            CREATE INDEX finance_fee_types_school_idx
            ON finance.fee_types (school_id)
        ');
        DB::statement('
            CREATE INDEX finance_fee_types_school_status_idx
            ON finance.fee_types (school_id, status)
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS finance.fee_types');
    }
};
