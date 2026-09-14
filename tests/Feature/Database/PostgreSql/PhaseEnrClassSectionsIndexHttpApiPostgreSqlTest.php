<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseEnrClassSectionsIndexHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_list_sections_for_class(): void
    {
        $schoolId = $this->createSchool('SCH-ENR-SEC-U01', 'ENR List Sections');
        $yearId = $this->createAcademicYear('AY-ENR-SEC-U01');
        $class = $this->createClassForSchool($schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);

        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->getJson('/api/v1/enrollment/classes/'.$class->id.'/sections')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (int) $section->id)
            ->assertJsonPath('data.0.class_id', (int) $class->id)
            ->assertJsonPath('data.0.school_id', $schoolId)
            ->assertJsonPath('data.0.code', $section->code)
            ->assertJsonPath('data.0.status', 1);
    }
}
