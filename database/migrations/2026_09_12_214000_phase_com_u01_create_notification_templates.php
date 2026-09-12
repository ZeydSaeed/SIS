<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase COM-U01 — Physicalize communication.notification_templates (+ school_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS communication');

        DB::statement("
            CREATE TABLE communication.notification_templates (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                code VARCHAR(50) NOT NULL,
                name VARCHAR(255) NOT NULL,
                channel SMALLINT NOT NULL,
                subject_template TEXT,
                body_template TEXT NOT NULL,
                is_active BOOLEAN NOT NULL DEFAULT true,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT communication_templates_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT communication_templates_channel_check
                    CHECK (channel IN (1, 2, 3, 9)),
                CONSTRAINT communication_templates_school_code_uq
                    UNIQUE (school_id, code)
            )
        ");

        DB::statement('
            CREATE INDEX communication_templates_school_idx
            ON communication.notification_templates (school_id)
        ');
        DB::statement('
            CREATE INDEX communication_templates_school_active_idx
            ON communication.notification_templates (school_id, is_active)
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS communication.notification_templates');
    }
};
