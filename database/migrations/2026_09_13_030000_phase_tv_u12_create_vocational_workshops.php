<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase TV-U12 — vocational.workshops with safety capacity CHECK + FORCE RLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS vocational');

        DB::statement("
            CREATE TABLE IF NOT EXISTS vocational.workshops (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                code VARCHAR(20) NOT NULL,
                name VARCHAR(255) NOT NULL,
                capacity SMALLINT NOT NULL,
                safety_capacity SMALLINT NOT NULL,
                room_id BIGINT,
                status SMALLINT NOT NULL DEFAULT 1,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT vocational_workshops_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT vocational_workshops_room_fk
                    FOREIGN KEY (room_id) REFERENCES organization.rooms(id) ON DELETE SET NULL,
                CONSTRAINT vocational_workshops_capacity_check
                    CHECK (capacity > 0),
                CONSTRAINT vocational_workshops_safety_capacity_check
                    CHECK (safety_capacity > 0),
                CONSTRAINT vocational_workshops_safety_le_capacity_check
                    CHECK (safety_capacity <= capacity),
                CONSTRAINT vocational_workshops_status_check
                    CHECK (status IN (1, 2)),
                CONSTRAINT vocational_workshops_school_code_uq
                    UNIQUE (school_id, code)
            )
        ");

        DB::statement('
            CREATE INDEX IF NOT EXISTS vocational_workshops_school_idx
            ON vocational.workshops (school_id)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS vocational_workshops_school_status_idx
            ON vocational.workshops (school_id, status)
        ');

        DB::statement('ALTER TABLE vocational.workshops ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE vocational.workshops FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS workshops_school_isolation ON vocational.workshops');
        DB::statement("
            CREATE POLICY workshops_school_isolation ON vocational.workshops
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");

        DB::statement("
            CREATE OR REPLACE FUNCTION vocational.reject_workshops_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of vocational.workshops is forbidden';
            END; $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS workshops_reject_delete ON vocational.workshops');
        DB::statement('
            CREATE TRIGGER workshops_reject_delete
            BEFORE DELETE ON vocational.workshops
            FOR EACH ROW EXECUTE FUNCTION vocational.reject_workshops_delete()
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS workshops_reject_delete ON vocational.workshops');
        DB::statement('DROP FUNCTION IF EXISTS vocational.reject_workshops_delete()');
        DB::statement('DROP POLICY IF EXISTS workshops_school_isolation ON vocational.workshops');
        DB::statement('DROP TABLE IF EXISTS vocational.workshops');
    }
};
