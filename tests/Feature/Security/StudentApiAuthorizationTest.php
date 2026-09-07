<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\TestCase;

class StudentApiAuthorizationTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;

    #[Test]
    public function unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/students')->assertUnauthorized();
        $this->postJson('/api/v1/students', [])->assertUnauthorized();
        $this->getJson('/api/v1/students/search?q=test')->assertUnauthorized();
    }

    #[Test]
    public function authenticated_user_without_permission_is_forbidden(): void
    {
        $this->actingAsAuthenticatedWithoutPermissions();

        $this->getJson('/api/v1/students')->assertForbidden();
        $this->getJson('/api/v1/students/search?q=test')->assertForbidden();
        $this->postJson('/api/v1/students', [
            'first_name' => 'Blocked',
            'last_name' => 'User',
            'gender' => 1,
            'birth_date' => '2010-01-01',
        ])->assertForbidden();
    }

    #[Test]
    public function student_manager_can_access_student_endpoints(): void
    {
        $this->actingAsStudentManager();

        $create = $this->postJson('/api/v1/students', [
            'first_name' => 'Secured',
            'last_name' => 'Student',
            'gender' => 1,
            'birth_date' => '2010-06-01',
            'national_id' => 'NAT-SEC-001',
            'student_code' => 'STU-SEC-001',
        ]);

        $create->assertCreated();
        $studentId = (int) $create->json('data.id');

        $this->getJson("/api/v1/students/{$studentId}")
            ->assertOk()
            ->assertJsonPath('data.national_id', 'NAT-SEC-001');

        $this->getJson('/api/v1/students')->assertOk();
        $this->getJson('/api/v1/students/search?q=Secured')->assertOk();
    }

    #[Test]
    public function national_id_hidden_without_pii_permission(): void
    {
        $this->actingAsStudentManager();

        $create = $this->postJson('/api/v1/students', [
            'first_name' => 'Viewer',
            'last_name' => 'Target',
            'gender' => 2,
            'birth_date' => '2011-02-02',
            'national_id' => 'NAT-HIDDEN-002',
            'student_code' => 'STU-VIEW-002',
        ])->assertCreated();

        $studentId = (int) $create->json('data.id');

        $this->actingAsStudentViewer();

        $this->getJson("/api/v1/students/{$studentId}")
            ->assertOk()
            ->assertJsonMissingPath('data.national_id');
    }

    #[Test]
    public function health_endpoint_remains_public(): void
    {
        $this->getJson('/api/v1/health')->assertOk();
    }
}
