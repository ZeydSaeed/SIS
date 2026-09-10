<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2 — fail-closed RLS for admission (school isolation via period.school_id).
 * Raw SQL required: PostgreSQL RLS policies.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE admission.application_periods ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE admission.applications ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE admission.application_documents ENABLE ROW LEVEL SECURITY');

        DB::statement("
            CREATE POLICY admission_periods_school_isolation ON admission.application_periods
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");

        DB::statement("
            CREATE POLICY admission_applications_school_isolation ON admission.applications
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1
                    FROM admission.application_periods AS periods
                    WHERE periods.id = applications.application_period_id
                      AND periods.school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            )
        ");

        DB::statement("
            CREATE POLICY admission_documents_school_isolation ON admission.application_documents
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1
                    FROM admission.applications AS apps
                    INNER JOIN admission.application_periods AS periods
                        ON periods.id = apps.application_period_id
                    WHERE apps.id = application_documents.application_id
                      AND periods.school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            )
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS admission_documents_school_isolation ON admission.application_documents');
        DB::statement('DROP POLICY IF EXISTS admission_applications_school_isolation ON admission.applications');
        DB::statement('DROP POLICY IF EXISTS admission_periods_school_isolation ON admission.application_periods');
        DB::statement('ALTER TABLE admission.application_documents DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE admission.applications DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE admission.application_periods DISABLE ROW LEVEL SECURITY');
    }
};
