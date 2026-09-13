<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase TV-U13 — vocational.workshop_equipment catalog + FORCE RLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement("
            CREATE TABLE IF NOT EXISTS vocational.workshop_equipment (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                workshop_id BIGINT NOT NULL,
                code VARCHAR(40) NOT NULL,
                name VARCHAR(255) NOT NULL,
                quantity SMALLINT NOT NULL,
                status SMALLINT NOT NULL DEFAULT 1,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT vocational_workshop_equipment_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT vocational_workshop_equipment_workshop_fk
                    FOREIGN KEY (workshop_id) REFERENCES vocational.workshops(id) ON DELETE RESTRICT,
                CONSTRAINT vocational_workshop_equipment_quantity_check
                    CHECK (quantity > 0),
                CONSTRAINT vocational_workshop_equipment_status_check
                    CHECK (status IN (1, 2)),
                CONSTRAINT vocational_workshop_equipment_workshop_code_uq
                    UNIQUE (workshop_id, code)
            )
        ");

        DB::statement('
            CREATE INDEX IF NOT EXISTS vocational_workshop_equipment_school_idx
            ON vocational.workshop_equipment (school_id)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS vocational_workshop_equipment_workshop_idx
            ON vocational.workshop_equipment (workshop_id, status)
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION vocational.reject_workshop_equipment_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard DELETE of vocational.workshop_equipment is forbidden'
                    USING ERRCODE = 'check_violation';
            END; $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS workshop_equipment_reject_delete ON vocational.workshop_equipment');
        DB::statement("
            CREATE TRIGGER workshop_equipment_reject_delete
            BEFORE DELETE ON vocational.workshop_equipment
            FOR EACH ROW EXECUTE FUNCTION vocational.reject_workshop_equipment_delete()
        ");

        DB::statement('ALTER TABLE vocational.workshop_equipment ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE vocational.workshop_equipment FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS workshop_equipment_school_isolation ON vocational.workshop_equipment');
        DB::statement("
            CREATE POLICY workshop_equipment_school_isolation ON vocational.workshop_equipment
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS workshop_equipment_school_isolation ON vocational.workshop_equipment');
        DB::statement('ALTER TABLE vocational.workshop_equipment NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE vocational.workshop_equipment DISABLE ROW LEVEL SECURITY');
        DB::statement('DROP TRIGGER IF EXISTS workshop_equipment_reject_delete ON vocational.workshop_equipment');
        DB::statement('DROP FUNCTION IF EXISTS vocational.reject_workshop_equipment_delete()');
        DB::statement('DROP TABLE IF EXISTS vocational.workshop_equipment');
    }
};
