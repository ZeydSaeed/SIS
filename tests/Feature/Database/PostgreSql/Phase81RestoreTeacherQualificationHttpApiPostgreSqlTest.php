<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Teachers\ValueObjects\QualificationStatus;
use App\Domain\Teachers\ValueObjects\QualificationType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase81RestoreTeacherQualificationHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_restore_voided_qualification(): void
    {
        $schoolId = $this->createSchool('SCH-81-R1', 'Teachers Restore 1');
        $yearId = $this->createAcademicYear('AY-81-R1');
        $this->actingAsTeachersManagerForSchool($schoolId);

        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-81-R1',
            'first_name' => 'Restore',
            'last_name' => 'Teacher',
        ], ['X-Idempotency-Key' => '81r-reg'])->json('data.teacher_id');

        $qualificationId = (int) $this->postJson('/api/v1/teachers/'.$teacherId.'/qualifications', [
            'academic_year_id' => $yearId,
            'qualification_type' => QualificationType::Degree,
            'title' => 'M.Ed',
        ], ['X-Idempotency-Key' => '81r-qual'])->json('data.qualification_id');

        $this->postJson('/api/v1/teachers/'.$teacherId.'/qualifications/'.$qualificationId.'/void', [
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => '81r-void'])->assertOk();

        $restore = $this->postJson('/api/v1/teachers/'.$teacherId.'/qualifications/'.$qualificationId.'/restore', [
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => '81r-restore'])->assertOk();

        $this->assertSame($qualificationId, (int) $restore->json('data.qualification_id'));

        $this->assertDatabaseHas(SchemaHelper::qualified('teachers', 'teacher_qualifications'), [
            'id' => $qualificationId,
            'status' => QualificationStatus::Active,
            'effective_to' => null,
        ]);
    }
}
