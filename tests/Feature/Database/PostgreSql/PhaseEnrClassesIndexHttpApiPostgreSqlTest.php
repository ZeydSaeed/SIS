<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseEnrClassesIndexHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_list_classes_filtered_by_academic_year(): void
    {
        $schoolId = $this->createSchool('SCH-ENR-CLS-U01', 'ENR List Classes');
        $yearId = $this->createAcademicYear('AY-ENR-CLS-U01');
        $otherYearId = $this->createAcademicYear('AY-ENR-CLS-U01B');
        $class = $this->createClassForSchool($schoolId, $yearId);
        $this->createClassForSchool($schoolId, $otherYearId);

        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->getJson('/api/v1/enrollment/classes?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (int) $class->id)
            ->assertJsonPath('data.0.school_id', $schoolId)
            ->assertJsonPath('data.0.academic_year_id', $yearId)
            ->assertJsonPath('data.0.status', 1);
    }
}
