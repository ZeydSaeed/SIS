<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Teachers\ValueObjects\QualificationStatus;
use App\Domain\Teachers\ValueObjects\QualificationType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase81VoidTeacherQualificationHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_void_qualification_idempotently(): void
    {
        $schoolId = $this->createSchool('SCH-81-V1', 'Teachers Void 1');
        $yearId = $this->createAcademicYear('AY-81-V1');
        $this->actingAsTeachersManagerForSchool($schoolId);

        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-81-V1',
            'first_name' => 'Void',
            'last_name' => 'Teacher',
        ], ['X-Idempotency-Key' => '81v-reg'])->json('data.teacher_id');

        $qualificationId = (int) $this->postJson('/api/v1/teachers/'.$teacherId.'/qualifications', [
            'academic_year_id' => $yearId,
            'qualification_type' => QualificationType::Degree,
            'title' => 'B.Ed',
        ], ['X-Idempotency-Key' => '81v-qual'])->json('data.qualification_id');

        $this->postJson('/api/v1/teachers/'.$teacherId.'/qualifications/'.$qualificationId.'/void', [
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => '81v-void'])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', false);

        $this->postJson('/api/v1/teachers/'.$teacherId.'/qualifications/'.$qualificationId.'/void', [
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => '81v-void'])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true);

        $this->postJson('/api/v1/teachers/'.$teacherId.'/qualifications/'.$qualificationId.'/void', [
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => '81v-void-2'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'teachers.qualification_not_active');

        $this->getJson('/api/v1/teachers/'.$teacherId.'/qualifications?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonPath('data.0.id', $qualificationId)
            ->assertJsonPath('data.0.status', QualificationStatus::Voided);

        $this->assertDatabaseHas(SchemaHelper::qualified('teachers', 'teacher_qualifications'), [
            'id' => $qualificationId,
            'status' => QualificationStatus::Voided,
        ]);
    }
}
