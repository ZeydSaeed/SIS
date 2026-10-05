<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admission periods belong to the academic year only; each application belongs to
 * the school chosen on the application (its own tenant column).
 *
 * - admission.applications.school_id (NOT NULL, FK schools) — backfilled from the period.
 *   target_school_id stays as the mirror of school_id for existing readers.
 * - admission.application_periods.school_id becomes nullable; existing periods are shared.
 * - RLS: periods visible inside any school context; applications/documents isolated on
 *   applications.school_id; a narrow UPDATE policy lets the transfer flow move an
 *   application to the school named in app.transfer_target_school_id.
 * - admission.application_transfers — history of school / request kind / year changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $apps = SchemaHelper::qualified('admission', 'applications');
        $periods = SchemaHelper::qualified('admission', 'application_periods');
        $docs = SchemaHelper::qualified('admission', 'application_documents');
        $schools = SchemaHelper::qualified('organization', 'schools');
        $pg = SchemaHelper::isPostgreSql();

        Schema::table($apps, function (Blueprint $table) use ($schools): void {
            $table->foreignId('school_id')->nullable()->constrained($schools)->restrictOnDelete();
        });

        if ($pg) {
            // Owner-level backfill: FORCE RLS would hide every row without a school context.
            DB::statement("ALTER TABLE {$apps} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$periods} NO FORCE ROW LEVEL SECURITY");
            // Old policies reference periods.school_id — drop before the column changes.
            DB::statement("DROP POLICY IF EXISTS admission_periods_school_isolation ON {$periods}");
            DB::statement("DROP POLICY IF EXISTS admission_applications_school_isolation ON {$apps}");
            DB::statement("DROP POLICY IF EXISTS admission_documents_school_isolation ON {$docs}");
            DB::statement("
                UPDATE {$apps} AS apps
                SET school_id = periods.school_id
                FROM {$periods} AS periods
                WHERE periods.id = apps.application_period_id
                  AND apps.school_id IS NULL
            ");
        } else {
            DB::statement("
                UPDATE {$apps}
                SET school_id = (
                    SELECT periods.school_id FROM {$periods} AS periods
                    WHERE periods.id = {$apps}.application_period_id
                )
                WHERE school_id IS NULL
            ");
        }
        DB::table($apps)->whereNull('target_school_id')->update(['target_school_id' => DB::raw('school_id')]);

        Schema::table($apps, function (Blueprint $table): void {
            $table->unsignedBigInteger('school_id')->nullable(false)->change();
            $table->index(['school_id', 'application_period_id', 'status'], 'applications_school_period_status_idx');
        });

        Schema::table($periods, function (Blueprint $table): void {
            $table->unsignedBigInteger('school_id')->nullable()->change();
            $table->index(['academic_year_id', 'status'], 'application_periods_year_status_idx');
        });
        DB::table($periods)->update(['school_id' => null]);

        Schema::create(SchemaHelper::qualified('admission', 'application_transfers'), function (Blueprint $table) use ($apps, $periods, $schools): void {
            $table->id();
            $table->foreignId('application_id')->constrained($apps)->restrictOnDelete();
            $table->foreignId('from_school_id')->constrained($schools)->restrictOnDelete();
            $table->foreignId('to_school_id')->constrained($schools)->restrictOnDelete();
            $table->smallInteger('from_request_kind');
            $table->smallInteger('to_request_kind');
            $table->foreignId('from_period_id')->constrained($periods)->restrictOnDelete();
            $table->foreignId('to_period_id')->constrained($periods)->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('application_id');
        });

        if (! $pg) {
            return;
        }

        $transfers = SchemaHelper::qualified('admission', 'application_transfers');
        DB::statement("ALTER TABLE {$transfers} ADD CONSTRAINT application_transfers_request_kind_chk CHECK (from_request_kind IN (1, 2) AND to_request_kind IN (1, 2))");
        DB::statement("ALTER TABLE {$transfers} ADD CONSTRAINT application_transfers_changes_chk CHECK (from_school_id <> to_school_id OR from_request_kind <> to_request_kind OR from_period_id <> to_period_id)");

        $current = "NULLIF(current_setting('app.current_school_id', true), '')";
        $target = "NULLIF(current_setting('app.transfer_target_school_id', true), '')";

        // Shared periods: readable/writable inside any school context (fail-closed without one).
        DB::statement("
            CREATE POLICY admission_periods_context ON {$periods}
            USING ({$current} IS NOT NULL)
        ");

        DB::statement("
            CREATE POLICY admission_applications_school_isolation ON {$apps}
            USING ({$current} IS NOT NULL AND school_id = {$current}::BIGINT)
        ");

        // Transfer: the row is visible in the source school and may land in the target school.
        DB::statement("
            CREATE POLICY admission_applications_transfer ON {$apps}
            FOR UPDATE
            USING ({$current} IS NOT NULL AND school_id = {$current}::BIGINT)
            WITH CHECK ({$target} IS NOT NULL AND school_id = {$target}::BIGINT)
        ");

        DB::statement("
            CREATE POLICY admission_documents_school_isolation ON {$docs}
            USING (
                {$current} IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM {$apps} AS apps
                    WHERE apps.id = application_documents.application_id
                      AND apps.school_id = {$current}::BIGINT
                )
            )
        ");

        DB::statement("ALTER TABLE {$transfers} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$transfers} FORCE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY admission_application_transfers_school ON {$transfers}
            USING (
                {$current} IS NOT NULL
                AND {$current}::BIGINT IN (from_school_id, to_school_id)
            )
        ");
        DB::statement("
            CREATE OR REPLACE FUNCTION admission.reject_application_transfer_delete() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'admission.application_transfers is append-only';
            END;
            $$ LANGUAGE plpgsql
        ");
        DB::statement("
            CREATE TRIGGER application_transfers_no_delete
            BEFORE DELETE OR UPDATE ON {$transfers}
            FOR EACH ROW EXECUTE FUNCTION admission.reject_application_transfer_delete()
        ");

        DB::statement("ALTER TABLE {$apps} FORCE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$periods} FORCE ROW LEVEL SECURITY");
    }

    public function down(): void
    {
        $apps = SchemaHelper::qualified('admission', 'applications');
        $periods = SchemaHelper::qualified('admission', 'application_periods');
        $docs = SchemaHelper::qualified('admission', 'application_documents');
        $transfers = SchemaHelper::qualified('admission', 'application_transfers');
        $pg = SchemaHelper::isPostgreSql();

        if ($pg) {
            DB::statement("ALTER TABLE {$apps} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$periods} NO FORCE ROW LEVEL SECURITY");
            DB::statement("DROP TRIGGER IF EXISTS application_transfers_no_delete ON {$transfers}");
            DB::statement('DROP FUNCTION IF EXISTS admission.reject_application_transfer_delete()');
        }

        Schema::dropIfExists($transfers);

        // Re-own each shared period by the school of its first application (or the first school).
        $fallbackSchool = DB::table(SchemaHelper::qualified('organization', 'schools'))->orderBy('id')->value('id');
        foreach (DB::table($periods)->whereNull('school_id')->pluck('id') as $periodId) {
            $schoolId = DB::table($apps)->where('application_period_id', $periodId)->orderBy('id')->value('school_id');
            DB::table($periods)->where('id', $periodId)->update(['school_id' => $schoolId ?? $fallbackSchool]);
        }

        if ($pg) {
            DB::statement("DROP POLICY IF EXISTS admission_periods_context ON {$periods}");
            DB::statement("DROP POLICY IF EXISTS admission_applications_transfer ON {$apps}");
            DB::statement("DROP POLICY IF EXISTS admission_applications_school_isolation ON {$apps}");
            DB::statement("DROP POLICY IF EXISTS admission_documents_school_isolation ON {$docs}");
        }

        Schema::table($periods, function (Blueprint $table): void {
            $table->dropIndex('application_periods_year_status_idx');
            $table->unsignedBigInteger('school_id')->nullable(false)->change();
        });

        Schema::table($apps, function (Blueprint $table): void {
            $table->dropIndex('applications_school_period_status_idx');
            $table->dropConstrainedForeignId('school_id');
        });

        if (! $pg) {
            return;
        }

        DB::statement("
            CREATE POLICY admission_periods_school_isolation ON {$periods}
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");
        DB::statement("
            CREATE POLICY admission_applications_school_isolation ON {$apps}
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM {$periods} AS periods
                    WHERE periods.id = applications.application_period_id
                      AND periods.school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            )
        ");
        DB::statement("
            CREATE POLICY admission_documents_school_isolation ON {$docs}
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM {$apps} AS apps
                    INNER JOIN {$periods} AS periods ON periods.id = apps.application_period_id
                    WHERE apps.id = application_documents.application_id
                      AND periods.school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            )
        ");
        DB::statement("ALTER TABLE {$apps} FORCE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$periods} FORCE ROW LEVEL SECURITY");
    }
};
