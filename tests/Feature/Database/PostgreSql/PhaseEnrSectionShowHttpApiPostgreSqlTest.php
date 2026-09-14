<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseEnrSectionShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_section(): void
    {
        $schoolId = $this->createSchool('SCH-ENR-SEC-U02', 'ENR Show Section');
        $yearId = $this->createAcademicYear('AY-ENR-SEC-U02');
        $class = $this->createClassForSchool($schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);

        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->getJson('/api/v1/enrollment/sections/'.$section->id)
            ->assertOk()
            ->assertJsonPath('data.id', (int) $section->id)
            ->assertJsonPath('data.class_id', (int) $class->id)
            ->assertJsonPath('data.school_id', $schoolId)
            ->assertJsonPath('data.code', $section->code)
            ->assertJsonPath('data.name', $section->name)
            ->assertJsonPath('data.status', 1);
    }
}
