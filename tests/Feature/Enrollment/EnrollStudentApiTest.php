<?php

namespace Tests\Feature\Enrollment;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\TestCase;

class EnrollStudentApiTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;

    #[Test]
    public function enrollment_manager_can_enroll_student_in_own_school(): void
    {
        $schoolId = $this->createSchool('SCHOOL-A', 'School A');
        $yearId = $this->createAcademicYear();
        $class = $this->createClassForSchool($schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);
        $student = $this->createStudentForSchool($schoolId);

        $this->actingAsEnrollmentManager(schoolId: $schoolId);

        $response = $this->postJson('/api/v1/enrollments', [
            'student_id' => $student->id,
            'academic_year_id' => $yearId,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'effective_from' => '2026-09-01',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'enrollment_number']]);

        $this->getJson('/api/v1/enrollments/'.$response->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.student_id', $student->id);
    }

    #[Test]
    public function enrollment_manager_can_list_enrollments_for_own_school(): void
    {
        $schoolId = $this->createSchool('SCHOOL-A', 'School A');
        $yearId = $this->createAcademicYear();
        $class = $this->createClassForSchool($schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);
        $student = $this->createStudentForSchool($schoolId);

        $this->actingAsEnrollmentManager(schoolId: $schoolId);

        $this->postJson('/api/v1/enrollments', [
            'student_id' => $student->id,
            'academic_year_id' => $yearId,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'effective_from' => '2026-09-01',
        ])->assertCreated();

        $this->getJson('/api/v1/enrollments')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }
}
