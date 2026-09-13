<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Teachers\ValueObjects\TeacherStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase87ReactivateTeacherHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_reactivate_teacher(): void
    {
        $schoolId = $this->createSchool('SCH-8-R1', 'Teacher Reactivate');
        $yearId = $this->createAcademicYear('AY-8-R1');
        $this->actingAsTeachersManagerForSchool($schoolId);

        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-8-R-001',
            'first_name' => 'Sara',
            'last_name' => 'Ali',
        ], ['X-Idempotency-Key' => 't87-reg'])->json('data.teacher_id');

        $this->postJson('/api/v1/teachers/'.$teacherId.'/deactivate', [], [
            'X-Idempotency-Key' => 't87-deact',
        ])->assertOk()->assertJsonPath('data.status', TeacherStatus::Inactive);

        $reactivate = $this->postJson('/api/v1/teachers/'.$teacherId.'/reactivate', [], [
            'X-Idempotency-Key' => 't87-react',
        ])->assertOk()->assertJsonPath('data.status', TeacherStatus::Active);

        $this->assertSame($teacherId, (int) $reactivate->json('data.teacher_id'));

        $this->assertDatabaseHas(SchemaHelper::qualified('teachers', 'teachers'), [
            'id' => $teacherId,
            'status' => TeacherStatus::Active,
        ]);
    }
}
