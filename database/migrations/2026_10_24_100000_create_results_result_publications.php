<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * EXAMIS A1 — results.result_publications: the record that an official annual result / year GPA of a class (or section)
 * was PUBLISHED to students. Publication never mutates result rows. One active publication per scope; withdrawal is a
 * status change with a reason; rows are never deleted. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement("
            CREATE TABLE results.result_publications (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                academic_year_id BIGINT NOT NULL,
                class_id BIGINT NOT NULL,
                section_id BIGINT,
                kind VARCHAR(16) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'published',
                participant_count INT NOT NULL,
                source_fingerprint VARCHAR(128) NOT NULL,
                published_snapshot JSONB NOT NULL,
                published_by BIGINT,
                published_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                withdrawn_by BIGINT,
                withdrawn_at TIMESTAMPTZ,
                withdraw_reason TEXT,
                correlation_id VARCHAR(64),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT result_publications_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT result_publications_year_fk
                    FOREIGN KEY (academic_year_id) REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                CONSTRAINT result_publications_class_fk
                    FOREIGN KEY (class_id) REFERENCES enrollment.classes(id) ON DELETE RESTRICT,
                CONSTRAINT result_publications_section_fk
                    FOREIGN KEY (section_id) REFERENCES enrollment.sections(id) ON DELETE RESTRICT,
                CONSTRAINT result_publications_published_by_fk
                    FOREIGN KEY (published_by) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT result_publications_withdrawn_by_fk
                    FOREIGN KEY (withdrawn_by) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT result_publications_kind_check CHECK (kind IN ('annual', 'gpa')),
                CONSTRAINT result_publications_status_check CHECK (status IN ('published', 'withdrawn')),
                CONSTRAINT result_publications_participants_check CHECK (participant_count >= 1),
                CONSTRAINT result_publications_withdrawn_check CHECK (
                    (status = 'published' AND withdrawn_at IS NULL AND withdraw_reason IS NULL)
                    OR (status = 'withdrawn' AND withdrawn_at IS NOT NULL AND length(btrim(withdraw_reason)) > 0)
                )
            )
        ");

        DB::statement("
            CREATE UNIQUE INDEX result_publications_active_scope_unique
            ON results.result_publications (school_id, academic_year_id, class_id, COALESCE(section_id, 0), kind)
            WHERE status = 'published'
        ");
        DB::statement('
            CREATE INDEX result_publications_school_year_idx
            ON results.result_publications (school_id, academic_year_id, class_id)
        ');

        DB::statement('
            CREATE FUNCTION results.result_publications_guard() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = \'DELETE\' THEN
                    RAISE EXCEPTION \'results.result_publications rows are never deleted: withdraw instead\';
                END IF;
                IF OLD.status = \'withdrawn\' THEN
                    RAISE EXCEPTION \'a withdrawn publication is final: publish again as a new row\';
                END IF;
                IF NEW.school_id <> OLD.school_id OR NEW.academic_year_id <> OLD.academic_year_id
                    OR NEW.class_id <> OLD.class_id OR NEW.section_id IS DISTINCT FROM OLD.section_id
                    OR NEW.kind <> OLD.kind OR NEW.source_fingerprint <> OLD.source_fingerprint
                    OR NEW.published_snapshot <> OLD.published_snapshot OR NEW.published_at <> OLD.published_at
                    OR NEW.participant_count <> OLD.participant_count THEN
                    RAISE EXCEPTION \'a publication snapshot is immutable: withdraw it and publish again\';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        ');
        DB::statement('
            CREATE TRIGGER result_publications_guard
            BEFORE UPDATE OR DELETE ON results.result_publications
            FOR EACH ROW EXECUTE FUNCTION results.result_publications_guard()
        ');
        DB::statement('ALTER TABLE results.result_publications ENABLE ALWAYS TRIGGER result_publications_guard');

        DB::statement('ALTER TABLE results.result_publications ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE results.result_publications FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY result_publications_school_isolation ON results.result_publications
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

        DB::statement('DROP TABLE IF EXISTS results.result_publications');
        DB::statement('DROP FUNCTION IF EXISTS results.result_publications_guard()');
    }
};
