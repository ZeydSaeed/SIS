<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseEnrClassShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_class(): void
    {
        $schoolId = $this->createSchool('SCH-ENR-CLS-U02', 'ENR Show Class');
        $yearId = $this->createAcademicYear('AY-ENR-CLS-U02');
        $class = $this->createClassForSchool($schoolId, $yearId);

        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->getJson('/api/v1/enrollment/classes/'.$class->id)
            ->assertOk()
            ->assertJsonPath('data.id', (int) $class->id)
            ->assertJsonPath('data.school_id', $schoolId)
            ->assertJsonPath('data.academic_year_id', $yearId)
            ->assertJsonPath('data.code', $class->code)
            ->assertJsonPath('data.name', $class->name)
            ->assertJsonPath('data.status', 1);
    }
}
