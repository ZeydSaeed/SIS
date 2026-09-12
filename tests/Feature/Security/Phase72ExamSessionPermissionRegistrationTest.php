<?php

namespace Tests\Feature\Security;

use App\Security\Authorization\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\TestCase;

final class Phase72ExamSessionPermissionRegistrationTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;

    #[Test]
    public function phase_7_2_permissions_registered_on_grades_manager_only(): void
    {
        $schoolId = $this->createSchool('SCH-72-PERM', 'Phase 72 Perm School');
        $manager = $this->actingAsGradesManagerForSchool($schoolId);
        $teacher = $this->actingAsGradesTeacher(schoolId: $schoolId);
        $auth = $this->app->make(\App\Security\Authorization\Contracts\AuthorizationServiceInterface::class);

        $required = [
            Permission::EXAM_SESSION_CREATE,
            Permission::EXAM_SESSION_UPDATE,
            Permission::EXAM_SESSION_OPEN,
            Permission::EXAM_SESSION_CLOSE,
            Permission::EXAM_ENROLLMENT_CREATE,
            Permission::EXAM_ENROLLMENT_UPDATE,
            Permission::EXAM_ENROLLMENT_CANCEL,
            Permission::EXAM_ENROLLMENT_PRESENT,
        ];

        $permissionAll = Permission::all();

        foreach ($required as $permission) {
            $this->assertArrayHasKey($permission, config('security.permissions'));
            $this->assertContains($permission, $permissionAll);
            $this->assertContains($permission, config('security.roles.grades_manager'));
            $this->assertTrue($auth->userHasPermission($manager, $permission));
            $this->assertFalse($auth->userHasPermission($teacher, $permission));
            $this->assertNotContains($permission, config('security.roles.grades_teacher'));
            $this->assertNotContains($permission, config('security.roles.grades_viewer'));
            $this->assertNotContains($permission, config('security.roles.attendance_manager'));
        }

        $this->assertArrayNotHasKey('exam.session.cancel', config('security.permissions'));
        $this->assertNotContains('exam.session.cancel', $permissionAll);
        $this->assertNotContains('exam.session.cancel', config('security.roles.grades_manager'));
        $this->assertNotContains('exam.session.cancel', config('security.roles.grades_teacher'));
    }
}
