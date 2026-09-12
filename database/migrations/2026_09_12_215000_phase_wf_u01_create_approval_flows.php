<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase WF-U01 — Physicalize workflow.approval_flows (+ school_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS workflow');

        DB::statement("
            CREATE TABLE workflow.approval_flows (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                name VARCHAR(255) NOT NULL,
                steps JSONB NOT NULL,
                is_active BOOLEAN NOT NULL DEFAULT true,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT workflow_approval_flows_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT workflow_approval_flows_steps_array_check
                    CHECK (jsonb_typeof(steps) = 'array' AND jsonb_array_length(steps) >= 1)
            )
        ");

        DB::statement('
            CREATE INDEX workflow_approval_flows_school_idx
            ON workflow.approval_flows (school_id)
        ');
        DB::statement('
            CREATE INDEX workflow_approval_flows_school_entity_idx
            ON workflow.approval_flows (school_id, entity_type)
        ');
        DB::statement('
            CREATE INDEX workflow_approval_flows_school_active_idx
            ON workflow.approval_flows (school_id, is_active)
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS workflow.approval_flows');
    }
};
