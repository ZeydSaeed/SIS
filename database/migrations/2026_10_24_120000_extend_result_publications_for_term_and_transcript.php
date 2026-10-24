<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * EXAMIS A1b — result publications also cover term results (per term + subject) and issued transcripts. Additive
 * ALTER of results.result_publications: two nullable scope columns, a wider kind CHECK, a scope-aware active-unique
 * index and a guard that also freezes the new columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE results.result_publications ADD COLUMN term_id BIGINT, ADD COLUMN subject_id BIGINT');
        DB::statement('
            ALTER TABLE results.result_publications
            ADD CONSTRAINT result_publications_term_fk FOREIGN KEY (term_id) REFERENCES academic.terms(id) ON DELETE RESTRICT,
            ADD CONSTRAINT result_publications_subject_fk FOREIGN KEY (subject_id) REFERENCES curriculum.subjects(id) ON DELETE RESTRICT
        ');
        DB::statement('ALTER TABLE results.result_publications DROP CONSTRAINT result_publications_kind_check');
        DB::statement("
            ALTER TABLE results.result_publications
            ADD CONSTRAINT result_publications_kind_check CHECK (kind IN ('annual', 'gpa', 'term', 'transcript')),
            ADD CONSTRAINT result_publications_term_scope_check
                CHECK ((kind = 'term') = (term_id IS NOT NULL AND subject_id IS NOT NULL)
                       AND (kind = 'term' OR (term_id IS NULL AND subject_id IS NULL)))
        ");
        DB::statement('DROP INDEX results.result_publications_active_scope_unique');
        DB::statement("
            CREATE UNIQUE INDEX result_publications_active_scope_unique
            ON results.result_publications (school_id, academic_year_id, class_id, COALESCE(section_id, 0), kind, COALESCE(term_id, 0), COALESCE(subject_id, 0))
            WHERE status = 'published'
        ");

        DB::statement('
            CREATE OR REPLACE FUNCTION results.result_publications_guard() RETURNS trigger AS $$
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
                    OR NEW.term_id IS DISTINCT FROM OLD.term_id OR NEW.subject_id IS DISTINCT FROM OLD.subject_id
                    OR NEW.published_snapshot <> OLD.published_snapshot OR NEW.published_at <> OLD.published_at
                    OR NEW.participant_count <> OLD.participant_count THEN
                    RAISE EXCEPTION \'a publication snapshot is immutable: withdraw it and publish again\';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        // Publications are audit records and are never deleted: refuse to roll back while term / transcript ones exist.
        if (DB::table('results.result_publications')->whereIn('kind', ['term', 'transcript'])->exists()) {
            throw new RuntimeException('Term / transcript publications exist; withdrawn rows cannot be deleted. Roll back by a reviewed data decision.');
        }
        DB::statement('DROP INDEX results.result_publications_active_scope_unique');
        DB::statement('ALTER TABLE results.result_publications DROP CONSTRAINT result_publications_term_scope_check, DROP CONSTRAINT result_publications_kind_check');
        DB::statement("ALTER TABLE results.result_publications ADD CONSTRAINT result_publications_kind_check CHECK (kind IN ('annual', 'gpa'))");
        DB::statement('ALTER TABLE results.result_publications DROP COLUMN term_id, DROP COLUMN subject_id');
        DB::statement("
            CREATE UNIQUE INDEX result_publications_active_scope_unique
            ON results.result_publications (school_id, academic_year_id, class_id, COALESCE(section_id, 0), kind)
            WHERE status = 'published'
        ");
    }
};
