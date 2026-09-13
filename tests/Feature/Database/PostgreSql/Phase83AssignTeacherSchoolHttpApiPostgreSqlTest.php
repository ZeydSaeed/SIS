<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase83AssignTeacherSchoolHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_assign_teacher_to_second_school(): void
    {
        $sourceSchoolId = $this->createSchool('SCH-83-A', 'Source A');
        $targetSchoolId = $this->createSchool('SCH-83-B', 'Target B');
        $yearId = $this->createAcademicYear('AY-83-1');

        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantTeachersManager($user, $sourceSchoolId);
        app(SecurityPermissionSeeder::class)->grantTeachersManager($user, $targetSchoolId);
        Sanctum::actingAs($user);

        $this->withHeader('X-School-Id', (string) $sourceSchoolId);
        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-83-MS',
            'first_name' => 'Multi',
            'last_name' => 'School',
        ], ['X-Idempotency-Key' => '83-reg'])
            ->assertCreated()
            ->json('data.teacher_id');

        $this->withHeader('X-School-Id', (string) $targetSchoolId);
        $assign = $this->postJson('/api/v1/teachers/'.$teacherId.'/assign-school', [
            'source_school_id' => $sourceSchoolId,
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => '83-assign'])
            ->assertCreated()
            ->assertJsonPath('data.teacher_id', $teacherId)
            ->assertJsonPath('data.school_id', $targetSchoolId)
            ->assertJsonPath('data.is_primary', false);

        $teacherSchoolId = (int) $assign->json('data.teacher_school_id');

        $this->postJson('/api/v1/teachers/'.$teacherId.'/assign-school', [
            'source_school_id' => $sourceSchoolId,
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => '83-assign'])
            ->assertOk()
            ->assertJsonPath('data.teacher_school_id', $teacherSchoolId)
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/teachers/'.$teacherId.'?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonPath('data.id', $teacherId)
            ->assertJsonPath('data.school_id', $targetSchoolId);

        $this->assertDatabaseHas(SchemaHelper::qualified('teachers', 'teacher_schools'), [
            'id' => $teacherSchoolId,
            'teacher_id' => $teacherId,
            'school_id' => $targetSchoolId,
            'academic_year_id' => $yearId,
            'is_primary' => false,
        ]);
    }

    #[Test]
    public function rejects_duplicate_target_membership(): void
    {
        $sourceSchoolId = $this->createSchool('SCH-83-C', 'Source C');
        $targetSchoolId = $this->createSchool('SCH-83-D', 'Target D');
        $yearId = $this->createAcademicYear('AY-83-2');

        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantTeachersManager($user, $sourceSchoolId);
        app(SecurityPermissionSeeder::class)->grantTeachersManager($user, $targetSchoolId);
        Sanctum::actingAs($user);

        $this->withHeader('X-School-Id', (string) $sourceSchoolId);
        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-83-DUP',
            'first_name' => 'Dup',
            'last_name' => 'Mem',
        ], ['X-Idempotency-Key' => '83-reg2'])->json('data.teacher_id');

        $this->withHeader('X-School-Id', (string) $targetSchoolId);
        $this->postJson('/api/v1/teachers/'.$teacherId.'/assign-school', [
            'source_school_id' => $sourceSchoolId,
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => '83-a1'])->assertCreated();

        $this->postJson('/api/v1/teachers/'.$teacherId.'/assign-school', [
            'source_school_id' => $sourceSchoolId,
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => '83-a2'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'teachers.already_in_target_school_year');
    }
}
