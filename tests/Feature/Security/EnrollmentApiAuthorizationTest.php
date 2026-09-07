<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\TestCase;

class EnrollmentApiAuthorizationTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;

    #[Test]
    public function unauthenticated_enrollment_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/enrollments')->assertUnauthorized();
        $this->postJson('/api/v1/enrollments', [])->assertUnauthorized();
    }

    #[Test]
    public function authenticated_user_without_enrollment_permission_is_forbidden(): void
    {
        $this->actingAsStudentViewer();

        $this->getJson('/api/v1/enrollments')->assertForbidden();
        $this->postJson('/api/v1/enrollments', [
            'student_id' => 1,
            'academic_year_id' => 1,
            'class_id' => 1,
            'section_id' => 1,
            'effective_from' => '2026-09-01',
        ])->assertForbidden();
    }

    #[Test]
    public function enrollment_manager_can_access_enrollment_endpoints(): void
    {
        $schoolId = $this->createSchool('SCHOOL-A', 'School A');
        $yearId = $this->createAcademicYear();
        $class = $this->createClassForSchool($schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);
        $student = $this->createStudentForSchool($schoolId);

        $this->actingAsEnrollmentManager(schoolId: $schoolId);

        $create = $this->postJson('/api/v1/enrollments', [
            'student_id' => $student->id,
            'academic_year_id' => $yearId,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'effective_from' => '2026-09-01',
        ])->assertCreated();

        $this->getJson('/api/v1/enrollments/'.$create->json('data.id'))->assertOk();
        $this->getJson('/api/v1/enrollments')->assertOk();
    }
}
