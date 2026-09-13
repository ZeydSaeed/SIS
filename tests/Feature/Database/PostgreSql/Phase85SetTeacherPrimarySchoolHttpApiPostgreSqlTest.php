<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase85SetTeacherPrimarySchoolHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_move_primary_between_schools(): void
    {
        $sourceSchoolId = $this->createSchool('SCH-85-A', 'Primary Source');
        $targetSchoolId = $this->createSchool('SCH-85-B', 'Primary Target');
        $yearId = $this->createAcademicYear('AY-85-1');

        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantTeachersManager($user, $sourceSchoolId);
        app(SecurityPermissionSeeder::class)->grantTeachersManager($user, $targetSchoolId);
        Sanctum::actingAs($user);

        $this->withHeader('X-School-Id', (string) $sourceSchoolId);
        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-85-P',
            'first_name' => 'Pri',
            'last_name' => 'Mary',
        ], ['X-Idempotency-Key' => '85-reg'])
            ->assertCreated()
            ->json('data.teacher_id');

        $this->withHeader('X-School-Id', (string) $targetSchoolId);
        $this->postJson('/api/v1/teachers/'.$teacherId.'/assign-school', [
            'source_school_id' => $sourceSchoolId,
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => '85-assign'])->assertCreated();

        $this->postJson('/api/v1/teachers/'.$teacherId.'/set-primary-school', [
            'source_school_id' => $sourceSchoolId,
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => '85-primary'])
            ->assertOk()
            ->assertJsonPath('data.teacher_id', $teacherId)
            ->assertJsonPath('data.school_id', $targetSchoolId)
            ->assertJsonPath('data.is_primary', true);

        $this->postJson('/api/v1/teachers/'.$teacherId.'/set-primary-school', [
            'source_school_id' => $sourceSchoolId,
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => '85-primary'])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true);

        $this->assertDatabaseHas(SchemaHelper::qualified('teachers', 'teacher_schools'), [
            'teacher_id' => $teacherId,
            'school_id' => $targetSchoolId,
            'academic_year_id' => $yearId,
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('teachers', 'teacher_schools'), [
            'teacher_id' => $teacherId,
            'school_id' => $sourceSchoolId,
            'academic_year_id' => $yearId,
            'is_primary' => false,
        ]);
    }
}
