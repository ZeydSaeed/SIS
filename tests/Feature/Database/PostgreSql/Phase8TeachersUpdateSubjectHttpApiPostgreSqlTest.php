<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Teachers\ValueObjects\TeacherStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase8TeachersUpdateSubjectHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_update_deactivate_and_manage_subjects(): void
    {
        $schoolId = $this->createSchool('SCH-8-U2', 'Teachers U2');
        $yearId = $this->createAcademicYear('AY-8-U2');
        $this->actingAsTeachersManagerForSchool($schoolId);

        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-8-U2-01',
            'first_name' => 'Old',
            'last_name' => 'Name',
        ], ['X-Idempotency-Key' => 'u2-reg'])->json('data.teacher_id');

        $this->patchJson('/api/v1/teachers/'.$teacherId, [
            'first_name' => 'New',
            'last_name' => 'Name',
            'specialization_field' => 'Physics',
        ], ['X-Idempotency-Key' => 'u2-upd'])
            ->assertOk()
            ->assertJsonPath('data.teacher_id', $teacherId);

        $this->getJson('/api/v1/teachers/'.$teacherId.'?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonPath('data.full_name', 'New Name')
            ->assertJsonPath('data.specialization_field', 'Physics');

        $subjectId = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'PHY-8-U2',
            'name' => 'Physics',
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/teachers/'.$teacherId.'/subjects', [
            'subject_id' => $subjectId,
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => 'u2-subj'])
            ->assertCreated()
            ->assertJsonPath('data.subject_id', $subjectId);

        $this->assertDatabaseHas(SchemaHelper::qualified('teachers', 'teacher_subjects'), [
            'teacher_id' => $teacherId,
            'subject_id' => $subjectId,
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
        ]);

        $this->deleteJson('/api/v1/teachers/'.$teacherId.'/subjects?subject_id='.$subjectId.'&academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonPath('data.was_present', true);

        $this->assertDatabaseMissing(SchemaHelper::qualified('teachers', 'teacher_subjects'), [
            'teacher_id' => $teacherId,
            'subject_id' => $subjectId,
            'school_id' => $schoolId,
        ]);

        $this->postJson('/api/v1/teachers/'.$teacherId.'/deactivate', [], [
            'X-Idempotency-Key' => 'u2-deact',
        ])->assertOk()->assertJsonPath('data.status', TeacherStatus::Inactive);

        $this->assertDatabaseHas(SchemaHelper::qualified('teachers', 'teachers'), [
            'id' => $teacherId,
            'status' => TeacherStatus::Inactive,
        ]);
    }

    #[Test]
    public function assign_subject_requires_school_year_membership(): void
    {
        $schoolId = $this->createSchool('SCH-8-U2B', 'Teachers U2B');
        $yearId = $this->createAcademicYear('AY-8-U2B');
        $otherYear = $this->createAcademicYear('AY-8-U2B-OTHER');
        $this->actingAsTeachersManagerForSchool($schoolId);

        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-8-U2-02',
            'first_name' => 'A',
            'last_name' => 'B',
        ], ['X-Idempotency-Key' => 'u2-reg2'])->json('data.teacher_id');

        $subjectId = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'MAT-8-U2',
            'name' => 'Math',
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/teachers/'.$teacherId.'/subjects', [
            'subject_id' => $subjectId,
            'academic_year_id' => $otherYear,
        ], ['X-Idempotency-Key' => 'u2-bad-year'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'teachers.not_in_school_year');
    }
}
