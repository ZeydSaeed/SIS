<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase WF-U04 — Physicalize workflow.approval_requests (+ school_id).
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
            CREATE TABLE workflow.approval_requests (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                flow_id BIGINT NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id BIGINT NOT NULL,
                current_step SMALLINT NOT NULL DEFAULT 1,
                status SMALLINT NOT NULL DEFAULT 1,
                requested_by BIGINT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                completed_at TIMESTAMPTZ,
                CONSTRAINT workflow_approval_requests_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT workflow_approval_requests_flow_fk
                    FOREIGN KEY (flow_id) REFERENCES workflow.approval_flows(id) ON DELETE RESTRICT,
                CONSTRAINT workflow_approval_requests_requested_by_fk
                    FOREIGN KEY (requested_by) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT workflow_approval_requests_step_check
                    CHECK (current_step >= 1),
                CONSTRAINT workflow_approval_requests_status_check
                    CHECK (status BETWEEN 1 AND 9)
            )
        ");

        DB::statement('
            CREATE INDEX workflow_approval_requests_school_idx
            ON workflow.approval_requests (school_id)
        ');
        DB::statement('
            CREATE INDEX workflow_approval_requests_entity_idx
            ON workflow.approval_requests (entity_type, entity_id)
        ');
        DB::statement('
            CREATE INDEX workflow_approval_requests_school_status_idx
            ON workflow.approval_requests (school_id, status)
        ');
        DB::statement('
            CREATE INDEX workflow_approval_requests_flow_idx
            ON workflow.approval_requests (flow_id)
        ');
        DB::statement('
            CREATE UNIQUE INDEX workflow_approval_requests_open_entity_uq
            ON workflow.approval_requests (school_id, entity_type, entity_id)
            WHERE status = 1
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS workflow.approval_requests');
    }
};
