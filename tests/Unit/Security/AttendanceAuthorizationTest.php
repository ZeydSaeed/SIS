<?php

namespace Tests\Unit\Security;

use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;
use App\Security\Authorization\SchoolScopeService;
use App\Security\Context\SchoolContext;
use App\Security\Policies\AttendancePolicy;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\TestCase;

class AttendanceAuthorizationTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;

    #[Test]
    public function deny_by_default_without_attendance_role(): void
    {
        $user = User::factory()->create();
        $policy = app(AttendancePolicy::class);

        $this->assertFalse($policy->viewAny($user));
        $this->assertFalse($policy->createSession($user));
        $this->assertFalse($policy->mark($user));
        $this->assertFalse($policy->correct($user));
        $this->assertFalse($policy->closeSession($user));
        $this->assertFalse($policy->cancelSession($user));
    }

    #[Test]
    public function attendance_viewer_can_view_only_with_school_context(): void
    {
        $schoolId = $this->createSchool('SCHOOL-A', 'School A');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantAttendanceViewer($user, $schoolId);
        Cache::forget("security.permissions.user.{$user->id}");
        Cache::forget("security.schools.user.{$user->id}");
        app(SchoolContext::class)->set($schoolId);

        $authorization = app(AuthorizationServiceInterface::class);
        $policy = app(AttendancePolicy::class);

        $this->assertTrue($authorization->userHasPermission($user, Permission::ATTENDANCE_VIEW));
        $this->assertFalse($authorization->userHasPermission($user, Permission::ATTENDANCE_MARK));
        $this->assertFalse($authorization->userHasPermission($user, Permission::ATTENDANCE_CORRECT));
        $this->assertTrue($policy->viewAny($user));
        $this->assertFalse($policy->mark($user));
        $this->assertFalse($policy->createSession($user));
        $this->assertFalse($policy->closeSession($user));
    }

    #[Test]
    public function attendance_teacher_can_mark_and_close_but_not_correct(): void
    {
        $schoolId = $this->createSchool('SCHOOL-A', 'School A');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantAttendanceTeacher($user, $schoolId);
        Cache::forget("security.permissions.user.{$user->id}");
        Cache::forget("security.schools.user.{$user->id}");
        app(SchoolContext::class)->set($schoolId);

        $authorization = app(AuthorizationServiceInterface::class);
        $policy = app(AttendancePolicy::class);

        $this->assertTrue($authorization->userHasPermission($user, Permission::ATTENDANCE_VIEW));
        $this->assertTrue($authorization->userHasPermission($user, Permission::ATTENDANCE_SESSION_CREATE));
        $this->assertTrue($authorization->userHasPermission($user, Permission::ATTENDANCE_MARK));
        $this->assertTrue($authorization->userHasPermission($user, Permission::ATTENDANCE_SESSION_CLOSE));
        $this->assertFalse($authorization->userHasPermission($user, Permission::ATTENDANCE_CORRECT));

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->createSession($user));
        $this->assertTrue($policy->mark($user));
        $this->assertTrue($policy->closeSession($user));
        $this->assertFalse($policy->correct($user));
        $this->assertFalse($policy->cancelSession($user));
    }

    #[Test]
    public function attendance_manager_can_correct_within_school_scope(): void
    {
        $schoolId = $this->createSchool('SCHOOL-A', 'School A');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantAttendanceManager($user, $schoolId);
        Cache::forget("security.permissions.user.{$user->id}");
        Cache::forget("security.schools.user.{$user->id}");
        app(SchoolContext::class)->set($schoolId);

        $authorization = app(AuthorizationServiceInterface::class);
        $policy = app(AttendancePolicy::class);

        $this->assertTrue($authorization->userHasPermission($user, Permission::ATTENDANCE_CORRECT));
        $this->assertTrue($authorization->userHasPermission($user, Permission::ATTENDANCE_SESSION_CANCEL));
        $this->assertTrue($policy->correct($user));
        $this->assertTrue($policy->cancelSession($user));
        $this->assertTrue($policy->mark($user));
        $this->assertTrue($policy->closeSession($user));
    }

    #[Test]
    public function attendance_role_without_school_context_is_denied_by_policy(): void
    {
        $schoolId = $this->createSchool('SCHOOL-A', 'School A');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantAttendanceTeacher($user, $schoolId);
        Cache::forget("security.permissions.user.{$user->id}");
        Cache::forget("security.schools.user.{$user->id}");
        app(SchoolContext::class)->clear();

        $policy = app(AttendancePolicy::class);

        $this->assertFalse($policy->viewAny($user));
        $this->assertFalse($policy->mark($user));
    }

    #[Test]
    public function attendance_permission_does_not_grant_cross_school_access(): void
    {
        $schoolA = $this->createSchool('SCHOOL-A', 'School A');
        $schoolB = $this->createSchool('SCHOOL-B', 'School B');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantAttendanceManager($user, $schoolA);
        Cache::forget("security.permissions.user.{$user->id}");
        Cache::forget("security.schools.user.{$user->id}");

        $scope = app(SchoolScopeService::class);
        $this->assertSame([$schoolA], $scope->allowedSchoolIds($user));
        $this->assertNotContains($schoolB, $scope->allowedSchoolIds($user));

        app(SchoolContext::class)->set($schoolB);
        $policy = app(AttendancePolicy::class);

        $this->assertFalse($policy->viewAny($user));
        $this->assertFalse($policy->correct($user));
        $this->assertFalse($policy->view($user, $schoolB));
    }

    #[Test]
    public function no_role_receives_attendance_administer_or_reopen_permissions(): void
    {
        $permissions = array_keys(config('security.permissions'));
        $this->assertNotContains('attendance.administer', $permissions);
        $this->assertNotContains('attendance.session.reopen', $permissions);
        $this->assertContains('attendance.session.cancel', $permissions);

        $roles = config('security.roles');
        foreach (['attendance_viewer', 'attendance_teacher', 'attendance_manager'] as $role) {
            $this->assertArrayHasKey($role, $roles);
            $this->assertNotContains('attendance.administer', $roles[$role]);
            $this->assertNotContains('attendance.session.reopen', $roles[$role]);
        }

        $this->assertNotContains('attendance.session.cancel', $roles['attendance_viewer']);
        $this->assertNotContains('attendance.session.cancel', $roles['attendance_teacher']);
        $this->assertContains('attendance.session.cancel', $roles['attendance_manager']);
    }
}
