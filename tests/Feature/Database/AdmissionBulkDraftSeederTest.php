<?php

namespace Tests\Feature\Database;

use App\Database\SchemaHelper;
use App\Domain\Admission\Services\ActiveAdmissionPeriodSummarizer;
use App\Domain\Admission\Services\AdmissionWorkflowProgressCalculator;
use App\Domain\Admission\ValueObjects\ApplicationPeriodStatus;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use Database\Seeders\AdmissionBulkDraftSeeder;
use Database\Seeders\Support\FoundationReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdmissionBulkDraftSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function seeder_creates_two_hundred_drafts_across_three_years_and_five_periods(): void
    {
        $this->seed(AdmissionBulkDraftSeeder::class);

        $applications = DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->where('application_number', 'like', AdmissionBulkDraftSeeder::APPLICATION_PREFIX.'%')
            ->get();

        $this->assertCount(AdmissionBulkDraftSeeder::COUNT, $applications);

        foreach ($applications as $row) {
            $this->assertSame(ApplicationStatus::Draft->value, (int) $row->status);
            $this->assertNull($row->submitted_at);
            $this->assertNull($row->student_id);
            $this->assertNotSame('', (string) $row->first_name);
            $this->assertNotSame('', (string) $row->last_name);
            $this->assertNotNull($row->national_id);
            $this->assertNotNull($row->grade_level_id);
        }

        $yearCodes = array_column(AdmissionBulkDraftSeeder::ACADEMIC_YEARS, 'code');
        $yearIds = DB::table(SchemaHelper::qualified('academic', 'academic_years'))
            ->whereIn('code', $yearCodes)
            ->pluck('id');
        $this->assertCount(3, $yearIds);

        $periods = DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->whereIn('academic_year_id', $yearIds)
            ->where('status', ApplicationPeriodStatus::Active->value)
            ->get();

        $this->assertCount(15, $periods);
        $this->assertEqualsCanonicalizing(
            AdmissionBulkDraftSeeder::PERIOD_NAMES,
            $periods->pluck('name')->unique()->values()->all(),
        );

        foreach (AdmissionBulkDraftSeeder::PERIOD_NAMES as $name) {
            $this->assertSame(
                3,
                $periods->where('name', $name)->count(),
                "Period {$name} should exist once per academic year.",
            );
        }

        $docs = DB::table(SchemaHelper::qualified('admission', 'application_documents'))
            ->whereIn('application_id', $applications->pluck('id')->all())
            ->count();
        $this->assertSame(AdmissionBulkDraftSeeder::COUNT * 3, $docs);

        $schoolId = (int) DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('code', FoundationReference::SCHOOL_CODE)
            ->value('id');
        $currentYearId = (int) DB::table(SchemaHelper::qualified('academic', 'academic_years'))
            ->where('code', FoundationReference::ACADEMIC_YEAR_CODE)
            ->value('id');

        $yearPeriods = $periods->where('academic_year_id', $currentYearId)->values();
        $this->assertCount(5, $yearPeriods);

        $countsByPeriod = [];
        foreach ($yearPeriods as $period) {
            $total = $applications->where('application_period_id', $period->id)->count();
            $countsByPeriod[(int) $period->id] = ['total' => $total, 'submitted' => 0];
            $this->assertGreaterThan(0, $total);
            $this->assertSame(
                AdmissionBulkDraftSeeder::PERIOD_MAX_APPLICATIONS,
                (int) $period->max_applications,
            );
            $this->assertSame(
                max(0, AdmissionBulkDraftSeeder::PERIOD_MAX_APPLICATIONS - $total),
                max(0, (int) $period->max_applications - $total),
            );
        }

        $summarizer = new ActiveAdmissionPeriodSummarizer;
        $summaries = $summarizer->summarize(
            $yearPeriods->map(static fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'status' => (int) $row->status,
                'max_applications' => (int) $row->max_applications,
                'start_date' => (string) $row->start_date,
            ])->all(),
            $countsByPeriod,
        );
        $this->assertCount(5, $summaries);
        foreach ($summaries as $summary) {
            $this->assertNotNull($summary['remaining']);
            $this->assertSame(
                max(0, (int) $summary['max_applications'] - (int) $summary['total_count']),
                $summary['remaining'],
            );
        }

        $statuses = $applications
            ->whereIn('application_period_id', $yearPeriods->pluck('id'))
            ->pluck('status')
            ->map(static fn ($status): int => (int) $status)
            ->all();
        $pipeline = array_map(
            static fn (ApplicationStatus $status): int => $status->value,
            ApplicationStatus::pipelineSteps(),
        );
        $progress = (new AdmissionWorkflowProgressCalculator)->calculate($pipeline, $statuses);
        $this->assertSame(100, $progress['stage_percents'][ApplicationStatus::Draft->value]);
        $this->assertSame(0, $progress['stage_percents'][ApplicationStatus::Submitted->value]);
        $this->assertGreaterThan(0, $progress['overall_percent']);
        $this->assertLessThanOrEqual(100, $progress['overall_percent']);

        $this->assertSame($schoolId, $schoolId);
    }

    #[Test]
    public function seeder_replaces_previous_admission_data(): void
    {
        $this->seed(AdmissionBulkDraftSeeder::class);
        $firstIds = DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->where('application_number', 'like', AdmissionBulkDraftSeeder::APPLICATION_PREFIX.'%')
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->seed(AdmissionBulkDraftSeeder::class);
        $secondCount = DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->where('application_number', 'like', AdmissionBulkDraftSeeder::APPLICATION_PREFIX.'%')
            ->count();
        $periodCount = DB::table(SchemaHelper::qualified('admission', 'application_periods'))->count();

        $this->assertSame(AdmissionBulkDraftSeeder::COUNT, $secondCount);
        $this->assertSame(15, $periodCount);
        $this->assertNotSame([], $firstIds);
    }
}
