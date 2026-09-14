<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseAcadGradeLevelsHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_list_and_show_grade_levels(): void
    {
        $schoolId = $this->createSchool('SCH-ACAD-GL', 'Grade Levels HTTP');
        $gradeLevelId = $this->createGradeLevel('G'.substr(uniqid(), -4));
        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->getJson('/api/v1/academic/grade-levels')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $gradeLevelId,
                'status' => 1,
            ]);

        $this->getJson('/api/v1/academic/grade-levels/'.$gradeLevelId)
            ->assertOk()
            ->assertJsonPath('data.id', $gradeLevelId)
            ->assertJsonPath('data.level_order', 10)
            ->assertJsonPath('data.education_stage', 2)
            ->assertJsonPath('data.status', 1);
    }

    #[Test]
    public function show_returns_404_for_missing_grade_level(): void
    {
        $schoolId = $this->createSchool('SCH-ACAD-GL-404', 'Grade Level Missing');
        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->getJson('/api/v1/academic/grade-levels/32001')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'academic.grade_level_not_found');
    }
}
