<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Domain\Teachers\ValueObjects\QualificationType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase81ShowTeacherQualificationHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_teacher_qualification(): void
    {
        $schoolId = $this->createSchool('SCH-81-U05', 'Teachers U05 Show');
        $yearId = $this->createAcademicYear('AY-81-U05');
        $this->actingAsTeachersManagerForSchool($schoolId);

        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-81-U05',
            'first_name' => 'Show',
            'last_name' => 'Qual',
        ], ['X-Idempotency-Key' => '81-u05-reg'])->json('data.teacher_id');

        $qualificationId = (int) $this->postJson('/api/v1/teachers/'.$teacherId.'/qualifications', [
            'academic_year_id' => $yearId,
            'qualification_type' => QualificationType::Degree,
            'title' => 'M.Sc Physics',
            'institution' => 'Tech University',
            'year_obtained' => 2015,
        ], ['X-Idempotency-Key' => '81-u05-qual'])->json('data.qualification_id');

        $this->getJson('/api/v1/teachers/'.$teacherId.'/qualifications/'.$qualificationId.'?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonPath('data.id', $qualificationId)
            ->assertJsonPath('data.teacher_id', $teacherId)
            ->assertJsonPath('data.title', 'M.Sc Physics')
            ->assertJsonPath('data.qualification_type', QualificationType::Degree);
    }
}
