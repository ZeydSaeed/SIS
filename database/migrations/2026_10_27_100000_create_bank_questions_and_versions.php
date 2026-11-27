<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * EXAMIS 7.3a (ADR-024, DEC-046..058) — the question bank: schema `bank`, `bank.questions` (stable identity of a
 * question of one subject of one school) and `bank.question_versions` (its content). A version is a working copy while
 * `draft`; its content freezes once submitted; approval needs a second person (four eyes, in the handler AND here);
 * approving supersedes the previous approved version. Nothing is ever deleted: a question retires. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS bank');

        DB::statement("
            CREATE TABLE bank.questions (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                subject_id BIGINT NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'active',
                created_by BIGINT,
                retired_by BIGINT,
                retired_at TIMESTAMPTZ,
                retire_reason TEXT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT questions_school_fk FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT questions_subject_fk FOREIGN KEY (subject_id) REFERENCES curriculum.subjects(id) ON DELETE RESTRICT,
                CONSTRAINT questions_created_by_fk FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT questions_retired_by_fk FOREIGN KEY (retired_by) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT questions_status_check CHECK (status IN ('active', 'retired')),
                CONSTRAINT questions_retired_check CHECK (
                    (status = 'retired' AND retired_at IS NOT NULL AND length(btrim(COALESCE(retire_reason, ''))) > 0)
                    OR (status = 'active' AND retired_at IS NULL AND retire_reason IS NULL)
                ),
                CONSTRAINT questions_id_school_unique UNIQUE (id, school_id)
            )
        ");
        // List page: questions of a school by subject and status.
        DB::statement('CREATE INDEX questions_school_subject_status_idx ON bank.questions (school_id, subject_id, status)');

        DB::statement("
            CREATE TABLE bank.question_versions (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                question_id BIGINT NOT NULL,
                school_id BIGINT NOT NULL,
                version_no INT NOT NULL,
                type VARCHAR(24) NOT NULL,
                stem TEXT NOT NULL,
                payload JSONB NOT NULL DEFAULT '{}'::jsonb,
                max_mark NUMERIC(6,2) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'draft',
                created_by BIGINT NOT NULL,
                submitted_by BIGINT,
                submitted_at TIMESTAMPTZ,
                decided_by BIGINT,
                decided_at TIMESTAMPTZ,
                decision_note TEXT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT question_versions_question_fk
                    FOREIGN KEY (question_id, school_id) REFERENCES bank.questions(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT question_versions_created_by_fk FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE RESTRICT,
                CONSTRAINT question_versions_submitted_by_fk FOREIGN KEY (submitted_by) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT question_versions_decided_by_fk FOREIGN KEY (decided_by) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT question_versions_no_unique UNIQUE (question_id, version_no),
                CONSTRAINT question_versions_version_no_check CHECK (version_no >= 1),
                CONSTRAINT question_versions_type_check CHECK (type IN ('single_choice', 'multiple_choice', 'true_false', 'long_answer')),
                CONSTRAINT question_versions_stem_check CHECK (length(btrim(stem)) > 0 AND length(stem) <= 5000),
                CONSTRAINT question_versions_payload_check CHECK (jsonb_typeof(payload) = 'object'),
                CONSTRAINT question_versions_max_mark_check CHECK (max_mark > 0 AND max_mark <= 1000),
                CONSTRAINT question_versions_status_check
                    CHECK (status IN ('draft', 'in_review', 'approved', 'rejected', 'superseded')),
                CONSTRAINT question_versions_submitted_check
                    CHECK (status = 'draft' OR (submitted_at IS NOT NULL AND submitted_by IS NOT NULL)),
                CONSTRAINT question_versions_decided_check CHECK (
                    (status IN ('approved', 'rejected', 'superseded') AND decided_at IS NOT NULL AND decided_by IS NOT NULL)
                    OR (status IN ('draft', 'in_review') AND decided_at IS NULL AND decided_by IS NULL)
                ),
                CONSTRAINT question_versions_rejected_note_check
                    -- COALESCE: a NULL note would make the CHECK unknown, which PostgreSQL accepts.
                    CHECK (status <> 'rejected' OR length(btrim(COALESCE(decision_note, ''))) > 0),
                -- Four eyes (DEC-047/056): the approver is never the author of the version.
                CONSTRAINT question_versions_four_eyes_check
                    CHECK (status NOT IN ('approved', 'superseded') OR decided_by <> created_by)
            )
        ");
        DB::statement("
            CREATE UNIQUE INDEX question_versions_one_open_unique
            ON bank.question_versions (question_id) WHERE status IN ('draft', 'in_review')
        ");
        DB::statement("
            CREATE UNIQUE INDEX question_versions_one_approved_unique
            ON bank.question_versions (question_id) WHERE status = 'approved'
        ");
        // Review queue of a school (status filter).
        DB::statement('CREATE INDEX question_versions_school_status_idx ON bank.question_versions (school_id, status)');

        DB::statement('
            CREATE FUNCTION bank.questions_guard() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = \'DELETE\' THEN
                    RAISE EXCEPTION \'bank.questions rows are never deleted\';
                END IF;
                IF OLD.status = \'retired\' THEN
                    RAISE EXCEPTION \'a retired question is final\';
                END IF;
                IF NEW.school_id <> OLD.school_id OR NEW.subject_id <> OLD.subject_id
                    OR NEW.created_by IS DISTINCT FROM OLD.created_by OR NEW.created_at <> OLD.created_at THEN
                    RAISE EXCEPTION \'the identity of a question is immutable\';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        ');
        DB::statement('CREATE TRIGGER questions_guard BEFORE UPDATE OR DELETE ON bank.questions FOR EACH ROW EXECUTE FUNCTION bank.questions_guard()');
        DB::statement('ALTER TABLE bank.questions ENABLE ALWAYS TRIGGER questions_guard');

        DB::statement('
            CREATE FUNCTION bank.question_versions_guard() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = \'DELETE\' THEN
                    RAISE EXCEPTION \'bank.question_versions rows are never deleted\';
                END IF;
                IF OLD.status IN (\'rejected\', \'superseded\') THEN
                    RAISE EXCEPTION \'a rejected or superseded question version is final\';
                END IF;
                IF NEW.question_id <> OLD.question_id OR NEW.school_id <> OLD.school_id OR NEW.version_no <> OLD.version_no
                    OR NEW.created_by <> OLD.created_by OR NEW.created_at <> OLD.created_at THEN
                    RAISE EXCEPTION \'the identity of a question version is immutable\';
                END IF;
                IF OLD.status <> \'draft\' AND (NEW.type <> OLD.type OR NEW.stem <> OLD.stem
                    OR NEW.payload <> OLD.payload OR NEW.max_mark <> OLD.max_mark) THEN
                    RAISE EXCEPTION \'the content of a submitted question version is immutable\';
                END IF;
                IF NEW.status <> OLD.status AND NOT (
                    (OLD.status = \'draft\' AND NEW.status = \'in_review\')
                    OR (OLD.status = \'in_review\' AND NEW.status IN (\'approved\', \'rejected\'))
                    OR (OLD.status = \'approved\' AND NEW.status = \'superseded\')
                ) THEN
                    RAISE EXCEPTION \'question version status % -> % is not an allowed transition\', OLD.status, NEW.status;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        ');
        DB::statement('CREATE TRIGGER question_versions_guard BEFORE UPDATE OR DELETE ON bank.question_versions FOR EACH ROW EXECUTE FUNCTION bank.question_versions_guard()');
        DB::statement('ALTER TABLE bank.question_versions ENABLE ALWAYS TRIGGER question_versions_guard');

        foreach (['questions', 'question_versions'] as $table) {
            DB::statement("ALTER TABLE bank.{$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE bank.{$table} FORCE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY {$table}_school_isolation ON bank.{$table}
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
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS bank.question_versions');
        DB::statement('DROP TABLE IF EXISTS bank.questions');
        DB::statement('DROP FUNCTION IF EXISTS bank.question_versions_guard()');
        DB::statement('DROP FUNCTION IF EXISTS bank.questions_guard()');
        DB::statement('DROP SCHEMA IF EXISTS bank');
    }
};
