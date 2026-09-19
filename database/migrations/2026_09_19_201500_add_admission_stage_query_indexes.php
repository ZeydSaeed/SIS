<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Admission stage list / filter indexes — measured query pattern:
 * JOIN periods ON school_id+academic_year_id+status=Active
 * + apps.status + ORDER BY created_at, id LIMIT/OFFSET.
 *
 * Baseline (200 apps): Seq Scan ~0.25ms. Index required for multi-year / million-row growth.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            CREATE INDEX IF NOT EXISTS application_periods_school_year_status_idx
            ON admission.application_periods (school_id, academic_year_id, status)
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS applications_period_status_created_idx
            ON admission.applications (application_period_id, status, created_at, id)
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS admission.applications_period_status_created_idx');
        DB::statement('DROP INDEX IF EXISTS admission.application_periods_school_year_status_idx');
    }
};
