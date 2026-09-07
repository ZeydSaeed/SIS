<?php

namespace Tests\Feature\Security;

use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\TestCase;

class SchoolContextRequiredTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_without_school_context_is_denied(): void
    {
        $user = $this->actingAsAuthenticatedWithoutPermissions();
        app(SecurityPermissionSeeder::class)->grantStudentManager($user, $this->createSchool('SCHOOL-A', 'School A'));

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/students')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'security.school_context_required');
    }

    #[Test]
    public function invalid_school_header_is_denied(): void
    {
        $schoolA = $this->createSchool('SCHOOL-A', 'School A');
        $schoolB = $this->createSchool('SCHOOL-B', 'School B');

        $user = $this->actingAsStudentManagerForSchool($schoolA);

        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolB);

        $this->getJson('/api/v1/students')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'security.school_context_required');
    }
}
