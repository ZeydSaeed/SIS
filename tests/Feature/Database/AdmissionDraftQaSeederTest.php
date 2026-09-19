<?php

namespace Tests\Feature\Database;

use App\Database\SchemaHelper;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use Database\Seeders\AdmissionDraftQaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdmissionDraftQaSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function seeder_creates_twenty_complete_draft_applications(): void
    {
        $this->seed(AdmissionDraftQaSeeder::class);

        $applications = DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->where('application_number', 'like', AdmissionDraftQaSeeder::APPLICATION_PREFIX.'%')
            ->orderBy('application_number')
            ->get();

        $this->assertCount(AdmissionDraftQaSeeder::COUNT, $applications);

        foreach ($applications as $row) {
            $this->assertSame(ApplicationStatus::Draft->value, (int) $row->status);
            $this->assertNotSame('', (string) $row->first_name);
            $this->assertNotSame('', (string) $row->father_name);
            $this->assertNotSame('', (string) $row->grandfather_name);
            $this->assertNotSame('', (string) $row->great_grandfather_name);
            $this->assertNotSame('', (string) $row->last_name);
            $this->assertNotSame('', (string) $row->mother_name);
            $this->assertNotSame('', (string) $row->maternal_father_name);
            $this->assertNotSame('', (string) $row->maternal_grandfather_name);
            $this->assertNotNull($row->national_id);
            $this->assertNotNull($row->birth_date);
            $this->assertNotNull($row->birth_place);
            $this->assertContains((int) $row->gender, [1, 2]);
            $this->assertNotNull($row->target_school_id);
            $this->assertNotNull($row->grade_level_id);
            $this->assertNotNull($row->intended_grade_name);
            $this->assertNotNull($row->department_name);
            $this->assertNotNull($row->specialization_id);
            $this->assertNotNull($row->specialization_name);
            $this->assertNotNull($row->governorate);
            $this->assertNotNull($row->neighborhood);
            $this->assertNotNull($row->notes);
            $this->assertNull($row->submitted_at);
            $this->assertNull($row->student_id);
        }

        $applicationIds = $applications->pluck('id')->all();
        $documentCount = DB::table(SchemaHelper::qualified('admission', 'application_documents'))
            ->whereIn('application_id', $applicationIds)
            ->count();

        $this->assertSame(AdmissionDraftQaSeeder::COUNT * 3, $documentCount);
    }

    #[Test]
    public function seeder_is_idempotent(): void
    {
        $this->seed(AdmissionDraftQaSeeder::class);
        $this->seed(AdmissionDraftQaSeeder::class);

        $this->assertSame(
            AdmissionDraftQaSeeder::COUNT,
            DB::table(SchemaHelper::qualified('admission', 'applications'))
                ->where('application_number', 'like', AdmissionDraftQaSeeder::APPLICATION_PREFIX.'%')
                ->count(),
        );
    }
}
