<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseAcadHolidaysHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_list_global_and_school_holidays(): void
    {
        $schoolId = $this->createSchool('SCH-ACAD-HOL', 'Holidays List');
        $otherSchoolId = $this->createSchool('SCH-ACAD-HOL-B', 'Holidays Other');
        $yearId = $this->createAcademicYear('AY-HOL-'.substr(uniqid(), -5));

        $globalId = (int) DB::table(SchemaHelper::qualified('academic', 'holidays'))->insertGetId([
            'academic_year_id' => $yearId,
            'school_id' => null,
            'name' => 'National Day',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-01',
            'holiday_type' => 1,
            'created_at' => now(),
        ]);
        $schoolHolidayId = (int) DB::table(SchemaHelper::qualified('academic', 'holidays'))->insertGetId([
            'academic_year_id' => $yearId,
            'school_id' => $schoolId,
            'name' => 'School Break',
            'start_date' => '2026-12-20',
            'end_date' => '2027-01-05',
            'holiday_type' => 2,
            'created_at' => now(),
        ]);
        DB::table(SchemaHelper::qualified('academic', 'holidays'))->insert([
            'academic_year_id' => $yearId,
            'school_id' => $otherSchoolId,
            'name' => 'Foreign Break',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-02',
            'holiday_type' => 2,
            'created_at' => now(),
        ]);

        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $response = $this->getJson('/api/v1/academic/holidays?academic_year_id='.$yearId)
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($globalId, $ids);
        $this->assertContains($schoolHolidayId, $ids);
        $this->assertCount(2, $ids);
    }
}
