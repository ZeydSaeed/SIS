<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTeachSubjShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_teacher_subject_assignment(): void
    {
        $schoolId = $this->createSchool('SCH-TS-U02', 'Teacher Subject Show');
        $yearId = $this->createAcademicYear('AY-TS-U02');
        $this->actingAsTeachersManagerForSchool($schoolId);

        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-TS-U02',
            'first_name' => 'Subj',
            'last_name' => 'Show',
        ], ['X-Idempotency-Key' => 'ts-u02-reg'])->json('data.teacher_id');

        $subjectId = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'MATH-TS-U02',
            'name' => 'Math',
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $assignmentId = (int) $this->postJson('/api/v1/teachers/'.$teacherId.'/subjects', [
            'subject_id' => $subjectId,
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => 'ts-u02-subj'])->json('data.assignment_id');

        $this->getJson('/api/v1/teachers/'.$teacherId.'/subjects/'.$assignmentId.'?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonPath('data.id', $assignmentId)
            ->assertJsonPath('data.teacher_id', $teacherId)
            ->assertJsonPath('data.subject_id', $subjectId)
            ->assertJsonPath('data.academic_year_id', $yearId)
            ->assertJsonPath('data.school_id', $schoolId);
    }
}
