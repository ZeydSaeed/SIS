<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseEnrEnrollmentReopenHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_reopen_cancelled_enrollment(): void
    {
        $schoolId = $this->createSchool('SCH-ENR-REOP', 'Enrollment Reopen');
        $yearId = $this->createAcademicYear('AY-ENR-REOP');
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId);
        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->postJson('/api/v1/enrollments/'.$enrollment->id.'/cancel', [
            'effective_to' => '2026-12-31',
        ], ['X-Idempotency-Key' => 'enr-reop-cancel'])->assertOk();

        $this->postJson('/api/v1/enrollments/'.$enrollment->id.'/reopen', [], [
            'X-Idempotency-Key' => 'enr-reop-on',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', EnrollmentStatus::ACTIVE);

        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollments'), [
            'id' => $enrollment->id,
            'status' => EnrollmentStatus::ACTIVE,
            'effective_to' => null,
        ]);
    }
}
