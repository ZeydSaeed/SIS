<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseEnrSectionDeactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_deactivate_section(): void
    {
        $schoolId = $this->createSchool('SCH-ENR-SEC-U03', 'ENR Deactivate Section');
        $yearId = $this->createAcademicYear('AY-ENR-SEC-U03');
        $class = $this->createClassForSchool($schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);

        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->postJson('/api/v1/enrollment/sections/'.$section->id.'/deactivate', [], [
            'X-Idempotency-Key' => 'enr-sec-u03-off',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', (int) $section->id);

        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'sections'), [
            'id' => $section->id,
            'status' => 2,
        ]);
    }
}
