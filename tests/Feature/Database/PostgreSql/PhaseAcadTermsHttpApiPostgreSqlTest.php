<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseAcadTermsHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_list_and_show_terms(): void
    {
        $schoolId = $this->createSchool('SCH-ACAD-TERM', 'Academic Terms HTTP');
        $yearId = $this->createAcademicYear('AY-TERM-'.substr(uniqid(), -5));
        $otherYearId = $this->createAcademicYear('AY-TERM-O'.substr(uniqid(), -4));

        $termId = (int) DB::table(SchemaHelper::qualified('academic', 'terms'))->insertGetId([
            'academic_year_id' => $yearId,
            'code' => 'T1',
            'name' => 'Term 1',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'term_order' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(SchemaHelper::qualified('academic', 'terms'))->insert([
            'academic_year_id' => $otherYearId,
            'code' => 'T1',
            'name' => 'Other Term',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'term_order' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->getJson('/api/v1/academic/terms?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $termId)
            ->assertJsonPath('data.0.code', 'T1')
            ->assertJsonPath('data.0.term_order', 1);

        $this->getJson('/api/v1/academic/terms/'.$termId)
            ->assertOk()
            ->assertJsonPath('data.id', $termId)
            ->assertJsonPath('data.academic_year_id', $yearId)
            ->assertJsonPath('data.name', 'Term 1');
    }

    #[Test]
    public function show_returns_404_for_missing_term(): void
    {
        $schoolId = $this->createSchool('SCH-TERM404', 'Term Missing');
        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->getJson('/api/v1/academic/terms/999999001')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'academic.term_not_found');
    }
}
