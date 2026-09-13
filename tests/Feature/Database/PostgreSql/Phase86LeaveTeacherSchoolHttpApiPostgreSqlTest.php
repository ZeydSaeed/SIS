<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase86LeaveTeacherSchoolHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_soft_leave_secondary_and_rejoin(): void
    {
        $primarySchoolId = $this->createSchool('SCH-86-A', 'Leave Primary');
        $secondarySchoolId = $this->createSchool('SCH-86-B', 'Leave Secondary');
        $yearId = $this->createAcademicYear('AY-86-1');

        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantTeachersManager($user, $primarySchoolId);
        app(SecurityPermissionSeeder::class)->grantTeachersManager($user, $secondarySchoolId);
        Sanctum::actingAs($user);

        $this->withHeader('X-School-Id', (string) $primarySchoolId);
        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-86-L',
            'first_name' => 'Lea',
            'last_name' => 'Ver',
        ], ['X-Idempotency-Key' => '86-reg'])
            ->assertCreated()
            ->json('data.teacher_id');

        $this->withHeader('X-School-Id', (string) $secondarySchoolId);
        $this->postJson('/api/v1/teachers/'.$teacherId.'/assign-school', [
            'source_school_id' => $primarySchoolId,
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => '86-assign'])->assertCreated();

        $this->withHeader('X-School-Id', (string) $primarySchoolId);
        $this->postJson('/api/v1/teachers/'.$teacherId.'/leave-school', [
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => '86-leave-primary'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'teachers.cannot_leave_primary_school');

        $this->withHeader('X-School-Id', (string) $secondarySchoolId);
        $this->postJson('/api/v1/teachers/'.$teacherId.'/leave-school', [
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => '86-leave'])
            ->assertOk()
            ->assertJsonPath('data.teacher_id', $teacherId)
            ->assertJsonPath('data.school_id', $secondarySchoolId)
            ->assertJsonPath('data.left', true);

        $this->postJson('/api/v1/teachers/'.$teacherId.'/leave-school', [
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => '86-leave'])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true);

        $row = DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))
            ->where('teacher_id', $teacherId)
            ->where('school_id', $secondarySchoolId)
            ->where('academic_year_id', $yearId)
            ->first(['left_at', 'is_primary']);
        $this->assertNotNull($row);
        $this->assertNotNull($row->left_at);
        $this->assertFalse((bool) $row->is_primary);

        $this->getJson('/api/v1/teachers?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonMissing(['employee_code' => 'T-86-L']);

        $this->postJson('/api/v1/teachers/'.$teacherId.'/assign-school', [
            'source_school_id' => $primarySchoolId,
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => '86-rejoin'])->assertCreated();

        $rejoined = DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))
            ->where('teacher_id', $teacherId)
            ->where('school_id', $secondarySchoolId)
            ->where('academic_year_id', $yearId)
            ->first(['left_at', 'is_primary']);
        $this->assertNotNull($rejoined);
        $this->assertNull($rejoined->left_at);
        $this->assertFalse((bool) $rejoined->is_primary);
    }
}
