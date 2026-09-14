<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseAcadYearsHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_create_list_and_show_academic_years(): void
    {
        $schoolId = $this->createSchool('SCH-ACAD-YR', 'Academic Years HTTP');
        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $code = 'AY-ACAD-'.substr(uniqid(), -6);

        $create = $this->postJson('/api/v1/academic/years', [
            'code' => $code,
            'name' => 'Academic Year '.$code,
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_current' => false,
        ], ['X-Idempotency-Key' => 'acad-yr-create-'.$code])
            ->assertCreated();

        $yearId = (int) $create->json('data.academic_year_id');
        $this->assertSame($code, $create->json('data.code'));
        $this->assertFalse((bool) $create->json('data.from_idempotency'));

        $this->postJson('/api/v1/academic/years', [
            'code' => $code,
            'name' => 'Academic Year '.$code,
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_current' => false,
        ], ['X-Idempotency-Key' => 'acad-yr-create-'.$code])
            ->assertOk()
            ->assertJsonPath('data.academic_year_id', $yearId)
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/academic/years')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $yearId,
                'code' => $code,
                'name' => 'Academic Year '.$code,
                'status' => 1,
            ]);

        $this->getJson('/api/v1/academic/years/'.$yearId)
            ->assertOk()
            ->assertJsonPath('data.id', $yearId)
            ->assertJsonPath('data.code', $code)
            ->assertJsonPath('data.start_date', '2026-09-01')
            ->assertJsonPath('data.end_date', '2027-06-30');

        $this->assertDatabaseHas(SchemaHelper::qualified('academic', 'academic_years'), [
            'id' => $yearId,
            'code' => $code,
        ]);
    }

    #[Test]
    public function viewer_cannot_create_academic_year(): void
    {
        $schoolId = $this->createSchool('SCH-ACAD-YR-V', 'Academic Years Viewer');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantEnrollmentViewer($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        $this->postJson('/api/v1/academic/years', [
            'code' => 'AY-DENY-'.substr(uniqid(), -4),
            'name' => 'Denied Year',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
        ], ['X-Idempotency-Key' => 'acad-yr-deny'])
            ->assertForbidden();
    }

    #[Test]
    public function show_returns_404_for_missing_academic_year(): void
    {
        $schoolId = $this->createSchool('SCH-ACAD-YR-404', 'Academic Year Missing');
        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->getJson('/api/v1/academic/years/999999001')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'academic.year_not_found');
    }
}
