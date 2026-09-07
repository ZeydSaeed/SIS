<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\TestCase;

class MassAssignmentProtectionTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;

    #[Test]
    public function create_student_rejects_role_injection(): void
    {
        $this->actingAsStudentManager();

        $this->postJson('/api/v1/students', [
            'first_name' => 'Role',
            'last_name' => 'Injection',
            'gender' => 1,
            'birth_date' => '2010-01-01',
            'role' => 'admin',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    #[Test]
    public function create_student_rejects_school_id_injection(): void
    {
        $schoolB = $this->createSchool('SCHOOL-B', 'School B');
        $this->actingAsStudentManager();

        $this->postJson('/api/v1/students', [
            'first_name' => 'School',
            'last_name' => 'Injection',
            'gender' => 1,
            'birth_date' => '2010-01-01',
            'school_id' => $schoolB,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['school_id']);
    }

    #[Test]
    public function create_student_rejects_permissions_injection(): void
    {
        $this->actingAsStudentManager();

        $this->postJson('/api/v1/students', [
            'first_name' => 'Perm',
            'last_name' => 'Injection',
            'gender' => 1,
            'birth_date' => '2010-01-01',
            'permissions' => ['students.create', 'security.manage_users'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions']);
    }

    #[Test]
    public function update_student_rejects_created_by_injection(): void
    {
        $this->actingAsStudentManager();

        $create = $this->postJson('/api/v1/students', [
            'first_name' => 'Created',
            'last_name' => 'By',
            'gender' => 1,
            'birth_date' => '2010-02-02',
        ])->assertCreated();

        $this->putJson('/api/v1/students/'.$create->json('data.id'), [
            'first_name' => 'Created',
            'last_name' => 'By',
            'gender' => 1,
            'birth_date' => '2010-02-02',
            'created_by' => 999,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['created_by']);
    }

    #[Test]
    public function basic_user_cannot_access_admin_probe_endpoint(): void
    {
        $this->actingAsStudentManager();

        $this->getJson('/api/v1/security/admin-probe')
            ->assertForbidden();
    }
}
