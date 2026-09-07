<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\TestCase;

class EnrollmentMassAssignmentTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;

    #[Test]
    public function enroll_rejects_school_id_injection(): void
    {
        $schoolB = $this->createSchool('SCHOOL-B', 'School B');
        $this->actingAsEnrollmentManager();

        $this->postJson('/api/v1/enrollments', [
            'student_id' => 1,
            'academic_year_id' => 1,
            'class_id' => 1,
            'section_id' => 1,
            'effective_from' => '2026-09-01',
            'school_id' => $schoolB,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['school_id']);
    }

    #[Test]
    public function enroll_rejects_enrolled_by_injection(): void
    {
        $this->actingAsEnrollmentManager();

        $this->postJson('/api/v1/enrollments', [
            'student_id' => 1,
            'academic_year_id' => 1,
            'class_id' => 1,
            'section_id' => 1,
            'effective_from' => '2026-09-01',
            'enrolled_by' => 999,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['enrolled_by']);
    }

    #[Test]
    public function enroll_rejects_status_injection(): void
    {
        $this->actingAsEnrollmentManager();

        $this->postJson('/api/v1/enrollments', [
            'student_id' => 1,
            'academic_year_id' => 1,
            'class_id' => 1,
            'section_id' => 1,
            'effective_from' => '2026-09-01',
            'status' => 99,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    #[Test]
    public function update_rejects_status_injection(): void
    {
        $schoolId = $this->createSchool('SCHOOL-A', 'School A');
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId);
        $this->actingAsEnrollmentManager(schoolId: $schoolId);

        $this->patchJson('/api/v1/enrollments/'.$enrollment->id, [
            'class_id' => 1,
            'section_id' => 1,
            'status' => 99,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    #[Test]
    public function cancel_rejects_status_injection(): void
    {
        $schoolId = $this->createSchool('SCHOOL-A', 'School A');
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId);
        $this->actingAsEnrollmentManager(schoolId: $schoolId);

        $this->postJson('/api/v1/enrollments/'.$enrollment->id.'/cancel', [
            'status' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }
}
