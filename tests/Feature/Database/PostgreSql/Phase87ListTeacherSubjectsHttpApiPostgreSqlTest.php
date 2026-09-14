<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase87ListTeacherSubjectsHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_list_teacher_subjects_for_school_year(): void
    {
        $schoolId = $this->createSchool('SCH-87-U01', 'Teachers Subjects List');
        $yearId = $this->createAcademicYear('AY-87-U01');
        $this->actingAsTeachersManagerForSchool($schoolId);

        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-87-U01',
            'first_name' => 'Subj',
            'last_name' => 'List',
        ], ['X-Idempotency-Key' => '87-u01-reg'])->json('data.teacher_id');

        $subjectId = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'ENG-87-U01',
            'name' => 'English',
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/teachers/'.$teacherId.'/subjects', [
            'subject_id' => $subjectId,
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => '87-u01-subj'])->assertCreated();

        $this->getJson('/api/v1/teachers/'.$teacherId.'/subjects?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.teacher_id', $teacherId)
            ->assertJsonPath('data.0.subject_id', $subjectId)
            ->assertJsonPath('data.0.academic_year_id', $yearId)
            ->assertJsonPath('data.0.school_id', $schoolId);
    }
}
