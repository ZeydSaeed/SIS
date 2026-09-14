<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseEnrClassDeactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_deactivate_class(): void
    {
        $schoolId = $this->createSchool('SCH-ENR-CLS-U03', 'ENR Deactivate Class');
        $yearId = $this->createAcademicYear('AY-ENR-CLS-U03');
        $class = $this->createClassForSchool($schoolId, $yearId);

        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->postJson('/api/v1/enrollment/classes/'.$class->id.'/deactivate', [], [
            'X-Idempotency-Key' => 'enr-cls-u03-off',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', (int) $class->id);

        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'classes'), [
            'id' => $class->id,
            'status' => 2,
        ]);
    }
}
