<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Domain\Hr\ValueObjects\JobPositionCategory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseHrEmployeeShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_employee(): void
    {
        $schoolId = $this->createSchool('SCH-HR-S1', 'HR Show Emp');
        $yearId = $this->createAcademicYear('AY-HR-S1');
        $this->actingAsHrManagerForSchool($schoolId);

        $positionId = (int) $this->postJson('/api/v1/hr/job-positions', [
            'code' => 'show-pos',
            'name' => 'Show Position',
            'category' => JobPositionCategory::Administrative,
        ], ['X-Idempotency-Key' => 'hr-show-pos'])->json('data.job_position_id');

        $employeeId = (int) $this->postJson('/api/v1/hr/employees', [
            'academic_year_id' => $yearId,
            'employee_number' => 'e-show-001',
            'first_name' => 'Show',
            'last_name' => 'Employee',
            'job_position_id' => $positionId,
        ], ['X-Idempotency-Key' => 'hr-show-emp'])->json('data.employee_id');

        $this->getJson('/api/v1/hr/employees/'.$employeeId)
            ->assertOk()
            ->assertJsonPath('data.id', $employeeId)
            ->assertJsonPath('data.employee_number', 'E-SHOW-001')
            ->assertJsonPath('data.first_name', 'Show');
    }
}
