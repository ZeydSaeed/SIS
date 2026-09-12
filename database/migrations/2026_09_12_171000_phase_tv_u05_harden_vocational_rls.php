<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase TV-U05 — FORCE RLS + reject hard DELETE on vocational.* tables.
 * Child tables isolate via specialization.school_id (no school_id column on tracks/subjects).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE vocational.specializations ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE vocational.specializations FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS specializations_school_isolation ON vocational.specializations');
        DB::statement("
            CREATE POLICY specializations_school_isolation ON vocational.specializations
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");

        DB::statement('ALTER TABLE vocational.tracks ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE vocational.tracks FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tracks_school_isolation ON vocational.tracks');
        DB::statement("
            CREATE POLICY tracks_school_isolation ON vocational.tracks
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM vocational.specializations sp
                    WHERE sp.id = tracks.specialization_id
                      AND sp.school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM vocational.specializations sp
                    WHERE sp.id = tracks.specialization_id
                      AND sp.school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            )
        ");

        DB::statement('ALTER TABLE vocational.specialization_subjects ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE vocational.specialization_subjects FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS specialization_subjects_school_isolation ON vocational.specialization_subjects');
        DB::statement("
            CREATE POLICY specialization_subjects_school_isolation ON vocational.specialization_subjects
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM vocational.specializations sp
                    WHERE sp.id = specialization_subjects.specialization_id
                      AND sp.school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM vocational.specializations sp
                    WHERE sp.id = specialization_subjects.specialization_id
                      AND sp.school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            )
        ");

        DB::statement("
            CREATE OR REPLACE FUNCTION vocational.reject_specializations_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of vocational.specializations is forbidden';
            END; $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS specializations_reject_delete ON vocational.specializations');
        DB::statement('
            CREATE TRIGGER specializations_reject_delete
            BEFORE DELETE ON vocational.specializations
            FOR EACH ROW EXECUTE FUNCTION vocational.reject_specializations_delete()
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION vocational.reject_tracks_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of vocational.tracks is forbidden';
            END; $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS tracks_reject_delete ON vocational.tracks');
        DB::statement('
            CREATE TRIGGER tracks_reject_delete
            BEFORE DELETE ON vocational.tracks
            FOR EACH ROW EXECUTE FUNCTION vocational.reject_tracks_delete()
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION vocational.reject_specialization_subjects_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of vocational.specialization_subjects is forbidden';
            END; $$
        ");
        DB::statement('DROP TRIGGER IF EXISTS specialization_subjects_reject_delete ON vocational.specialization_subjects');
        DB::statement('
            CREATE TRIGGER specialization_subjects_reject_delete
            BEFORE DELETE ON vocational.specialization_subjects
            FOR EACH ROW EXECUTE FUNCTION vocational.reject_specialization_subjects_delete()
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS specialization_subjects_reject_delete ON vocational.specialization_subjects');
        DB::statement('DROP FUNCTION IF EXISTS vocational.reject_specialization_subjects_delete()');
        DB::statement('DROP TRIGGER IF EXISTS tracks_reject_delete ON vocational.tracks');
        DB::statement('DROP FUNCTION IF EXISTS vocational.reject_tracks_delete()');
        DB::statement('DROP TRIGGER IF EXISTS specializations_reject_delete ON vocational.specializations');
        DB::statement('DROP FUNCTION IF EXISTS vocational.reject_specializations_delete()');

        DB::statement('DROP POLICY IF EXISTS specialization_subjects_school_isolation ON vocational.specialization_subjects');
        DB::statement('ALTER TABLE vocational.specialization_subjects NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE vocational.specialization_subjects DISABLE ROW LEVEL SECURITY');

        DB::statement('DROP POLICY IF EXISTS tracks_school_isolation ON vocational.tracks');
        DB::statement('ALTER TABLE vocational.tracks NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE vocational.tracks DISABLE ROW LEVEL SECURITY');

        DB::statement('DROP POLICY IF EXISTS specializations_school_isolation ON vocational.specializations');
        DB::statement('ALTER TABLE vocational.specializations NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE vocational.specializations DISABLE ROW LEVEL SECURITY');
    }
};
