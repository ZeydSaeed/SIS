<?php

namespace Tests\Unit\Security;

use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;
use App\Security\Context\SchoolContext;
use App\Security\Policies\StudentPolicy;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\TestCase;

class StudentPolicyTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;

    #[Test]
    public function deny_by_default_without_role_assignment(): void
    {
        $user = User::factory()->create();
        $policy = app(StudentPolicy::class);

        $this->assertFalse($policy->viewAny($user));
        $this->assertFalse($policy->create($user));
        $this->assertFalse($policy->viewPii($user));
    }

    #[Test]
    public function student_manager_has_expected_permissions_with_school_context(): void
    {
        $schoolId = $this->createSchool('SCHOOL-A', 'School A');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantStudentManager($user, $schoolId);
        app(SchoolContext::class)->set($schoolId);

        $authorization = app(AuthorizationServiceInterface::class);
        $policy = app(StudentPolicy::class);

        $this->assertTrue($authorization->userHasPermission($user, Permission::STUDENTS_VIEW));
        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($authorization->userHasPermission($user, Permission::STUDENTS_VIEW_PII));
    }

    #[Test]
    public function student_manager_without_school_context_is_denied(): void
    {
        $schoolId = $this->createSchool('SCHOOL-A', 'School A');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantStudentManager($user, $schoolId);
        app(SchoolContext::class)->clear();

        $policy = app(StudentPolicy::class);

        $this->assertFalse($policy->viewAny($user));
    }

    #[Test]
    public function student_viewer_cannot_view_pii(): void
    {
        $schoolId = $this->createSchool('SCHOOL-A', 'School A');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantStudentViewer($user, $schoolId);
        app(SchoolContext::class)->set($schoolId);

        $authorization = app(AuthorizationServiceInterface::class);

        $this->assertTrue($authorization->userHasPermission($user, Permission::STUDENTS_VIEW));
        $this->assertFalse($authorization->userHasPermission($user, Permission::STUDENTS_VIEW_PII));
    }
}
