<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseEnrClassReactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_reactivate_class(): void
    {
        $schoolId = $this->createSchool('SCH-ENR-CLS-U04', 'ENR Reactivate Class');
        $yearId = $this->createAcademicYear('AY-ENR-CLS-U04');
        $class = $this->createClassForSchool($schoolId, $yearId);

        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->postJson('/api/v1/enrollment/classes/'.$class->id.'/deactivate', [], [
            'X-Idempotency-Key' => 'enr-cls-u04-off',
        ])->assertOk();

        $this->postJson('/api/v1/enrollment/classes/'.$class->id.'/reactivate', [], [
            'X-Idempotency-Key' => 'enr-cls-u04-on',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', (int) $class->id);

        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'classes'), [
            'id' => $class->id,
            'status' => 1,
        ]);
    }
}
