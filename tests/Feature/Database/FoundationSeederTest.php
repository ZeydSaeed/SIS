<?php

namespace Tests\Feature\Database;

use App\Database\SchemaHelper;
use Database\Seeders\SisFoundationSeeder;
use Database\Seeders\Support\FoundationReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FoundationSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function foundation_seeder_creates_organization_and_academic_reference_data(): void
    {
        $this->seed(SisFoundationSeeder::class);

        $schoolTable = SchemaHelper::qualified('organization', 'schools');
        $yearTable = SchemaHelper::qualified('academic', 'academic_years');
        $gradeTable = SchemaHelper::qualified('academic', 'grade_levels');
        $termTable = SchemaHelper::qualified('academic', 'terms');

        $this->assertTrue(
            DB::table($schoolTable)->where('code', FoundationReference::SCHOOL_CODE)->exists()
        );

        $year = DB::table($yearTable)->where('code', FoundationReference::ACADEMIC_YEAR_CODE)->first();
        $this->assertNotNull($year);
        $this->assertTrue((bool) $year->is_current);

        $this->assertSame(3, DB::table($gradeTable)->count());
        $this->assertSame(2, DB::table($termTable)->where('academic_year_id', $year->id)->count());
    }

    #[Test]
    public function foundation_seeder_is_idempotent(): void
    {
        $this->seed(SisFoundationSeeder::class);
        $this->seed(SisFoundationSeeder::class);

        $schoolTable = SchemaHelper::qualified('organization', 'schools');
        $yearTable = SchemaHelper::qualified('academic', 'academic_years');

        $this->assertSame(1, DB::table($schoolTable)->where('code', FoundationReference::SCHOOL_CODE)->count());
        $this->assertSame(1, DB::table($yearTable)->where('code', FoundationReference::ACADEMIC_YEAR_CODE)->count());
    }
}
