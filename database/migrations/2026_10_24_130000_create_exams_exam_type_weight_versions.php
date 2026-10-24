<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * EXAMIS C — exams.exam_type_weight_versions: the weight of an exam type for ONE academic year, as numbered versions.
 * The base weight on exams.exam_types stays the default for years without a version. Versions are INSERT-only (a change
 * is a new version with a reason); stored results are never recalculated by creating one. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            CREATE TABLE exams.exam_type_weight_versions (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                exam_type_id BIGINT NOT NULL,
                academic_year_id BIGINT NOT NULL,
                version_no INT NOT NULL,
                weight_percentage SMALLINT NOT NULL,
                reason TEXT NOT NULL,
                created_by BIGINT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT exam_type_weight_versions_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT exam_type_weight_versions_type_fk
                    FOREIGN KEY (exam_type_id, school_id) REFERENCES exams.exam_types(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT exam_type_weight_versions_year_fk
                    FOREIGN KEY (academic_year_id) REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                CONSTRAINT exam_type_weight_versions_user_fk
                    FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT exam_type_weight_versions_weight_check CHECK (weight_percentage BETWEEN 0 AND 100),
                CONSTRAINT exam_type_weight_versions_version_check CHECK (version_no >= 1),
                CONSTRAINT exam_type_weight_versions_reason_check CHECK (length(btrim(reason)) > 0),
                CONSTRAINT exam_type_weight_versions_unique UNIQUE (exam_type_id, academic_year_id, version_no)
            )
        ');
        DB::statement('CREATE INDEX exam_type_weight_versions_school_year_idx ON exams.exam_type_weight_versions (school_id, academic_year_id)');

        DB::statement('
            CREATE FUNCTION exams.exam_type_weight_versions_guard() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION \'exams.exam_type_weight_versions is insert-only: add a new version instead\';
            END;
            $$ LANGUAGE plpgsql
        ');
        DB::statement('
            CREATE TRIGGER exam_type_weight_versions_guard
            BEFORE UPDATE OR DELETE ON exams.exam_type_weight_versions
            FOR EACH ROW EXECUTE FUNCTION exams.exam_type_weight_versions_guard()
        ');
        DB::statement('ALTER TABLE exams.exam_type_weight_versions ENABLE ALWAYS TRIGGER exam_type_weight_versions_guard');

        DB::statement('ALTER TABLE exams.exam_type_weight_versions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE exams.exam_type_weight_versions FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY exam_type_weight_versions_school_isolation ON exams.exam_type_weight_versions
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

        DB::statement('DROP TABLE IF EXISTS exams.exam_type_weight_versions');
        DB::statement('DROP FUNCTION IF EXISTS exams.exam_type_weight_versions_guard()');
    }
};
