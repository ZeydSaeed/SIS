<?php

namespace App\Console\Commands;

use App\Database\SchemaHelper;
use Database\Seeders\AdmissionEnrollmentWorkflowSeeder;
use Database\Seeders\Support\FoundationReference;
use Database\Seeders\Support\WorkflowDataWiper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Wipe demo school workflow data + seed 100 students end-to-end.
 *
 * Note: migrate:fresh is blocked on protected DB `sis` — use --wipe (default).
 *
 * php artisan sis:seed-admission-enrollment-workflow
 * php artisan sis:seed-admission-enrollment-workflow --wipe
 */
class SeedAdmissionEnrollmentWorkflowCommand extends Command
{
    protected $signature = 'sis:seed-admission-enrollment-workflow
                            {--wipe : Wipe demo-school admission/student/enrollment rows before seed (default true)}
                            {--no-wipe : Skip wipe}
                            {--max-attempts=3 : Retry loop until verification passes}';

    protected $description = 'Seed 100 unique students via vocational + academic-transfer admission through enrollment';

    public function handle(): int
    {
        $maxAttempts = max(1, (int) $this->option('max-attempts'));
        $attempt = 0;
        $lastError = null;
        $doWipe = ! $this->option('no-wipe');

        while ($attempt < $maxAttempts) {
            $attempt++;
            $this->components->info("Workflow seed attempt {$attempt}/{$maxAttempts}");

            try {
                if ($doWipe || $attempt > 1) {
                    $this->components->warn('Wiping demo-school admission/student/enrollment data (schema preserved)...');
                    app(WorkflowDataWiper::class)->wipeDemoSchool();
                }

                Artisan::call('db:seed', [
                    '--class' => AdmissionEnrollmentWorkflowSeeder::class,
                    '--force' => true,
                ]);
                $this->line(Artisan::output());

                if ($this->verify()) {
                    $this->components->info('Workflow verification PASSED (100%).');
                    $this->printSummary();

                    return self::SUCCESS;
                }

                $lastError = 'Verification counts did not match expected workflow totals.';
                $this->components->error($lastError);
            } catch (\Throwable $exception) {
                $lastError = $exception->getMessage();
                $this->components->error($lastError);
                report($exception);
            }
        }

        $this->components->error('Workflow seed failed after '.$maxAttempts.' attempts: '.($lastError ?? 'unknown'));

        return self::FAILURE;
    }

    private function verify(): bool
    {
        $schoolId = (int) DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('code', FoundationReference::SCHOOL_CODE)
            ->value('id');
        $yearId = (int) DB::table(SchemaHelper::qualified('academic', 'academic_years'))
            ->where('code', FoundationReference::ACADEMIC_YEAR_CODE)
            ->value('id');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $apps = (int) DB::table(SchemaHelper::qualified('admission', 'applications').' as a')
            ->join(SchemaHelper::qualified('admission', 'application_periods').' as p', 'p.id', '=', 'a.application_period_id')
            ->where('p.name', AdmissionEnrollmentWorkflowSeeder::PERIOD_NAME)
            ->count();
        $converted = (int) DB::table(SchemaHelper::qualified('admission', 'applications').' as a')
            ->join(SchemaHelper::qualified('admission', 'application_periods').' as p', 'p.id', '=', 'a.application_period_id')
            ->where('p.name', AdmissionEnrollmentWorkflowSeeder::PERIOD_NAME)
            ->where('a.status', 9)
            ->whereNotNull('a.student_id')
            ->count();
        $enrollments = (int) DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $yearId)
            ->where('status', 1)
            ->count();
        $vocational = (int) DB::table(SchemaHelper::qualified('admission', 'applications').' as a')
            ->join(SchemaHelper::qualified('admission', 'application_periods').' as p', 'p.id', '=', 'a.application_period_id')
            ->where('p.name', AdmissionEnrollmentWorkflowSeeder::PERIOD_NAME)
            ->where('a.request_kind', 2)
            ->count();
        $transfer = (int) DB::table(SchemaHelper::qualified('admission', 'applications').' as a')
            ->join(SchemaHelper::qualified('admission', 'application_periods').' as p', 'p.id', '=', 'a.application_period_id')
            ->where('p.name', AdmissionEnrollmentWorkflowSeeder::PERIOD_NAME)
            ->where('a.request_kind', 1)
            ->count();
        $docs = (int) DB::table(SchemaHelper::qualified('admission', 'application_documents').' as d')
            ->join(SchemaHelper::qualified('admission', 'applications').' as a', 'a.id', '=', 'd.application_id')
            ->join(SchemaHelper::qualified('admission', 'application_periods').' as p', 'p.id', '=', 'a.application_period_id')
            ->where('p.name', AdmissionEnrollmentWorkflowSeeder::PERIOD_NAME)
            ->count();
        $branches = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))
            ->where('school_id', $schoolId)
            ->count();
        $classes = (int) DB::table(SchemaHelper::qualified('enrollment', 'classes'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $yearId)
            ->count();

        $ok = $apps === AdmissionEnrollmentWorkflowSeeder::COUNT
            && $converted === AdmissionEnrollmentWorkflowSeeder::COUNT
            && $enrollments === AdmissionEnrollmentWorkflowSeeder::COUNT
            && $vocational === AdmissionEnrollmentWorkflowSeeder::VOCATIONAL_COUNT
            && $transfer === AdmissionEnrollmentWorkflowSeeder::TRANSFER_COUNT
            && $docs === AdmissionEnrollmentWorkflowSeeder::COUNT * count(AdmissionEnrollmentWorkflowSeeder::DOCUMENT_TYPES)
            && $branches >= 7
            && $classes === 3;

        $this->table(
            ['Metric', 'Expected', 'Actual', 'OK'],
            [
                ['applications', (string) AdmissionEnrollmentWorkflowSeeder::COUNT, (string) $apps, $apps === AdmissionEnrollmentWorkflowSeeder::COUNT ? 'yes' : 'no'],
                ['converted', (string) AdmissionEnrollmentWorkflowSeeder::COUNT, (string) $converted, $converted === AdmissionEnrollmentWorkflowSeeder::COUNT ? 'yes' : 'no'],
                ['enrollments (active)', (string) AdmissionEnrollmentWorkflowSeeder::COUNT, (string) $enrollments, $enrollments === AdmissionEnrollmentWorkflowSeeder::COUNT ? 'yes' : 'no'],
                ['vocational (kind=2)', (string) AdmissionEnrollmentWorkflowSeeder::VOCATIONAL_COUNT, (string) $vocational, $vocational === AdmissionEnrollmentWorkflowSeeder::VOCATIONAL_COUNT ? 'yes' : 'no'],
                ['transfer (kind=1)', (string) AdmissionEnrollmentWorkflowSeeder::TRANSFER_COUNT, (string) $transfer, $transfer === AdmissionEnrollmentWorkflowSeeder::TRANSFER_COUNT ? 'yes' : 'no'],
                ['documents', (string) (AdmissionEnrollmentWorkflowSeeder::COUNT * count(AdmissionEnrollmentWorkflowSeeder::DOCUMENT_TYPES)), (string) $docs, $docs === AdmissionEnrollmentWorkflowSeeder::COUNT * count(AdmissionEnrollmentWorkflowSeeder::DOCUMENT_TYPES) ? 'yes' : 'no'],
                ['branches', '>=7', (string) $branches, $branches >= 7 ? 'yes' : 'no'],
                ['classes', '3', (string) $classes, $classes === 3 ? 'yes' : 'no'],
            ],
        );

        return $ok;
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->components->info('SSOT: AdmissionCatalogReference.php ↔ admission-branch-catalog.ts ↔ sis-class-section-options');
        $this->line('  • 50 قبول مهني جديد (request_kind=2)');
        $this->line('  • 50 تحويل أكاديمي→مهني (request_kind=1)');
        $this->line('  • أسماء ومعرّفات وبقية الحقول فريدة؛ الفرع/الاختصاص/الصف/الشعبة قد تتكرر');
        $this->line('  • مستمسكات صغيرة ~10KB في storage/app/admission/{id}/');
        $this->line('  • المسار: طلب → مراجعة → قبول → طالب → تسجيل');
    }
}
