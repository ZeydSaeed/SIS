<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Domain\Hr\ValueObjects\JobPositionCategory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseHrJobPositionShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_job_position(): void
    {
        $schoolId = $this->createSchool('SCH-HR-PS1', 'HR Show Pos');
        $this->actingAsHrManagerForSchool($schoolId);

        $positionId = (int) $this->postJson('/api/v1/hr/job-positions', [
            'code' => 'pos-show',
            'name' => 'Position Show',
            'category' => JobPositionCategory::Administrative,
        ], ['X-Idempotency-Key' => 'hr-pos-show'])->json('data.job_position_id');

        $this->getJson('/api/v1/hr/job-positions/'.$positionId)
            ->assertOk()
            ->assertJsonPath('data.id', $positionId)
            ->assertJsonPath('data.code', 'POS-SHOW')
            ->assertJsonPath('data.name', 'Position Show');
    }
}
