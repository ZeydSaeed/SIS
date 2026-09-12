<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Teachers\ValueObjects\QualificationType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase81TeacherQualificationsHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_add_and_list_qualifications(): void
    {
        $schoolId = $this->createSchool('SCH-81-Q1', 'Teachers Q1');
        $yearId = $this->createAcademicYear('AY-81-Q1');
        $this->actingAsTeachersManagerForSchool($schoolId);

        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-81-Q1',
            'first_name' => 'Qual',
            'last_name' => 'Teacher',
        ], ['X-Idempotency-Key' => '81-reg'])->json('data.teacher_id');

        $create = $this->postJson('/api/v1/teachers/'.$teacherId.'/qualifications', [
            'academic_year_id' => $yearId,
            'qualification_type' => QualificationType::Degree,
            'title' => 'B.Sc Mathematics',
            'institution' => 'State University',
            'year_obtained' => 2018,
        ], ['X-Idempotency-Key' => '81-qual-1'])
            ->assertCreated()
            ->assertJsonPath('data.from_idempotency', false);

        $qualificationId = (int) $create->json('data.qualification_id');

        $this->postJson('/api/v1/teachers/'.$teacherId.'/qualifications', [
            'academic_year_id' => $yearId,
            'qualification_type' => QualificationType::Degree,
            'title' => 'B.Sc Mathematics',
            'institution' => 'State University',
            'year_obtained' => 2018,
        ], ['X-Idempotency-Key' => '81-qual-1'])
            ->assertOk()
            ->assertJsonPath('data.qualification_id', $qualificationId)
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/teachers/'.$teacherId.'/qualifications?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonPath('data.0.id', $qualificationId)
            ->assertJsonPath('data.0.title', 'B.Sc Mathematics')
            ->assertJsonPath('data.0.qualification_type', QualificationType::Degree)
            ->assertJsonPath('data.0.status', \App\Domain\Teachers\ValueObjects\QualificationStatus::Active);

        $this->assertDatabaseHas(SchemaHelper::qualified('teachers', 'teacher_qualifications'), [
            'id' => $qualificationId,
            'teacher_id' => $teacherId,
            'title' => 'B.Sc Mathematics',
        ]);
    }

    #[Test]
    public function viewer_can_list_but_cannot_add_qualification(): void
    {
        $schoolId = $this->createSchool('SCH-81-Q2', 'Teachers Q2');
        $yearId = $this->createAcademicYear('AY-81-Q2');
        $this->actingAsTeachersManagerForSchool($schoolId);

        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-81-Q2',
            'first_name' => 'V',
            'last_name' => 'W',
        ], ['X-Idempotency-Key' => '81-reg2'])->json('data.teacher_id');

        $this->actingAsTeachersViewerForSchool($schoolId);

        $this->postJson('/api/v1/teachers/'.$teacherId.'/qualifications', [
            'academic_year_id' => $yearId,
            'qualification_type' => QualificationType::Certificate,
            'title' => 'Denied',
        ], ['X-Idempotency-Key' => '81-deny'])
            ->assertForbidden();

        $this->getJson('/api/v1/teachers/'.$teacherId.'/qualifications?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    #[Test]
    public function qualification_add_requires_school_year_membership(): void
    {
        $schoolId = $this->createSchool('SCH-81-Q3', 'Teachers Q3');
        $yearId = $this->createAcademicYear('AY-81-Q3');
        $otherYear = $this->createAcademicYear('AY-81-Q3-OTHER');
        $this->actingAsTeachersManagerForSchool($schoolId);

        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-81-Q3',
            'first_name' => 'A',
            'last_name' => 'B',
        ], ['X-Idempotency-Key' => '81-reg3'])->json('data.teacher_id');

        $this->postJson('/api/v1/teachers/'.$teacherId.'/qualifications', [
            'academic_year_id' => $otherYear,
            'qualification_type' => QualificationType::Other,
            'title' => 'Wrong year',
        ], ['X-Idempotency-Key' => '81-wrong-year'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'teachers.not_in_school_year');
    }
}
