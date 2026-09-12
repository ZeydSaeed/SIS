<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase COM-U04 — Physicalize communication.messages (+ school_id; queue only).
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
            CREATE TABLE communication.messages (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                template_id BIGINT,
                recipient_type VARCHAR(50) NOT NULL,
                recipient_id BIGINT NOT NULL,
                channel SMALLINT NOT NULL,
                subject TEXT,
                body TEXT NOT NULL,
                status SMALLINT NOT NULL DEFAULT 1,
                sent_at TIMESTAMPTZ,
                idempotency_key VARCHAR(100) NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT communication_messages_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT communication_messages_template_fk
                    FOREIGN KEY (template_id) REFERENCES communication.notification_templates(id) ON DELETE RESTRICT,
                CONSTRAINT communication_messages_channel_check
                    CHECK (channel IN (1, 2, 3, 9)),
                CONSTRAINT communication_messages_status_check
                    CHECK (status BETWEEN 1 AND 9),
                CONSTRAINT communication_messages_idempotency_uq
                    UNIQUE (idempotency_key)
            )
        ");

        DB::statement('
            CREATE INDEX communication_messages_school_idx
            ON communication.messages (school_id)
        ');
        DB::statement('
            CREATE INDEX communication_messages_recipient_idx
            ON communication.messages (recipient_type, recipient_id)
        ');
        DB::statement('
            CREATE INDEX communication_messages_school_status_idx
            ON communication.messages (school_id, status)
        ');
        DB::statement('
            CREATE INDEX communication_messages_school_created_idx
            ON communication.messages (school_id, created_at)
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS communication.messages');
    }
};
