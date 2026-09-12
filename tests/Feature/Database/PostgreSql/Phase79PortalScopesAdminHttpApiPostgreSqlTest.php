<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use App\Security\Authorization\Permission;
use App\Domain\Portal\ValueObjects\PortalScopeType;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase79PortalScopesAdminHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function portal_scopes_manage_permission_is_registered(): void
    {
        $this->assertArrayHasKey(Permission::PORTAL_SCOPES_MANAGE, config('security.permissions'));
        $this->assertContains(Permission::PORTAL_SCOPES_MANAGE, Permission::all());
        $this->assertContains(Permission::PORTAL_SCOPES_MANAGE, config('security.roles.portal_scopes_manager'));
    }

    #[Test]
    public function manager_can_link_list_and_unlink_student_scope(): void
    {
        $schoolId = $this->createSchool('SCH-79-LINK', 'Scope Link');
        $yearId = $this->createAcademicYear('AY-79-LINK');
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId);
        $target = User::factory()->create();
        $this->actingAsPortalScopesManagerForSchool($schoolId);

        $this->postJson('/api/v1/portal/scopes', [
            'user_id' => $target->id,
            'scope_type' => PortalScopeType::STUDENT,
            'scope_id' => $enrollment->student_id,
        ], ['X-Idempotency-Key' => '79-link-student-1'])
            ->assertCreated()
            ->assertJsonPath('data.scope_type', PortalScopeType::STUDENT)
            ->assertJsonPath('data.scope_id', (int) $enrollment->student_id);

        $this->getJson('/api/v1/portal/scopes?user_id='.$target->id)
            ->assertOk()
            ->assertJsonPath('data.0.scope_type', PortalScopeType::STUDENT)
            ->assertJsonPath('data.0.scope_id', (int) $enrollment->student_id);

        $this->deleteJson('/api/v1/portal/scopes?user_id='.$target->id
            .'&scope_type='.PortalScopeType::STUDENT
            .'&scope_id='.$enrollment->student_id)
            ->assertOk()
            ->assertJsonPath('data.was_present', true);

        $this->assertDatabaseMissing(SchemaHelper::qualified('security', 'scopes'), [
            'user_id' => $target->id,
            'scope_type' => PortalScopeType::STUDENT,
            'scope_id' => $enrollment->student_id,
        ]);
    }

    #[Test]
    public function manager_can_link_guardian_when_linked_in_school(): void
    {
        $schoolId = $this->createSchool('SCH-79-G', 'Scope Guard');
        $yearId = $this->createAcademicYear('AY-79-G');
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId);
        $guardianId = $this->createGuardianLinkedToStudent((int) $enrollment->student_id);
        $target = User::factory()->create();
        $this->actingAsPortalScopesManagerForSchool($schoolId);

        $this->postJson('/api/v1/portal/scopes', [
            'user_id' => $target->id,
            'scope_type' => PortalScopeType::GUARDIAN,
            'scope_id' => $guardianId,
        ], ['X-Idempotency-Key' => '79-link-guard-1'])
            ->assertCreated()
            ->assertJsonPath('data.scope_type', PortalScopeType::GUARDIAN);
    }

    #[Test]
    public function student_not_in_school_is_rejected(): void
    {
        $schoolA = $this->createSchool('SCH-79-A', 'A');
        $schoolB = $this->createSchool('SCH-79-B', 'B');
        $enrollmentB = $this->createActiveEnrollmentForSchool($schoolB, $this->createAcademicYear('AY-79-B'));
        $target = User::factory()->create();
        $this->actingAsPortalScopesManagerForSchool($schoolA);

        $this->postJson('/api/v1/portal/scopes', [
            'user_id' => $target->id,
            'scope_type' => PortalScopeType::STUDENT,
            'scope_id' => $enrollmentB->student_id,
        ], ['X-Idempotency-Key' => '79-reject-school'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'portal.scopes.student_not_in_school');
    }

    #[Test]
    public function guardian_without_school_student_link_is_rejected(): void
    {
        $schoolId = $this->createSchool('SCH-79-GN', 'Guard No');
        $guardianId = (int) DB::table(SchemaHelper::qualified('guardians', 'guardians'))->insertGetId([
            'first_name' => 'No',
            'last_name' => 'Link',
            'full_name' => 'No Link',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $target = User::factory()->create();
        $this->actingAsPortalScopesManagerForSchool($schoolId);

        $this->postJson('/api/v1/portal/scopes', [
            'user_id' => $target->id,
            'scope_type' => PortalScopeType::GUARDIAN,
            'scope_id' => $guardianId,
        ], ['X-Idempotency-Key' => '79-reject-guard'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'portal.scopes.guardian_not_linked_in_school');
    }

    #[Test]
    public function portal_viewer_cannot_manage_scopes(): void
    {
        $schoolId = $this->createSchool('SCH-79-DENY', 'Deny');
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId);
        $target = User::factory()->create();
        $this->actingAsPortalResultsViewerForSchool($schoolId);

        $this->postJson('/api/v1/portal/scopes', [
            'user_id' => $target->id,
            'scope_type' => PortalScopeType::STUDENT,
            'scope_id' => $enrollment->student_id,
        ], ['X-Idempotency-Key' => '79-deny'])
            ->assertForbidden();
    }

    private function createGuardianLinkedToStudent(int $studentId): int
    {
        $guardianId = (int) DB::table(SchemaHelper::qualified('guardians', 'guardians'))->insertGetId([
            'first_name' => 'Parent',
            'last_name' => 'Admin',
            'full_name' => 'Parent Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(SchemaHelper::qualified('guardians', 'student_guardians'))->insert([
            'student_id' => $studentId,
            'guardian_id' => $guardianId,
            'relationship_type' => 1,
            'is_primary' => true,
            'is_emergency_contact' => false,
            'created_at' => now(),
        ]);

        return $guardianId;
    }
}
