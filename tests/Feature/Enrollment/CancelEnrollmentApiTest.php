<?php

namespace Tests\Feature\Enrollment;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\TestCase;

class CancelEnrollmentApiTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;

    #[Test]
    public function enrollment_manager_can_cancel_active_enrollment(): void
    {
        $schoolId = $this->createSchool('SCHOOL-A', 'School A');
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId);

        $this->actingAsEnrollmentManager(schoolId: $schoolId);

        $this->postJson('/api/v1/enrollments/'.$enrollment->id.'/cancel', [
            'effective_to' => '2026-09-15',
        ])->assertOk()
            ->assertJsonPath('data.effective_to', '2026-09-15')
            ->assertJsonPath('data.status', 2);

        $enrollment->refresh();
        $this->assertSame(2, $enrollment->status);
        $this->assertSame('2026-09-15', $enrollment->effective_to->format('Y-m-d'));
    }

    #[Test]
    public function cannot_cancel_enrollment_twice(): void
    {
        $schoolId = $this->createSchool('SCHOOL-A', 'School A');
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId);

        $this->actingAsEnrollmentManager(schoolId: $schoolId);

        $this->postJson('/api/v1/enrollments/'.$enrollment->id.'/cancel', [
            'effective_to' => '2026-09-15',
        ])->assertOk();

        $this->postJson('/api/v1/enrollments/'.$enrollment->id.'/cancel')
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'enrollment.not_active');
    }
}
