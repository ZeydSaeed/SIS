<?php

namespace Tests\Feature\Enrollment;

use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\TestCase;

class UpdateEnrollmentApiTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;

    #[Test]
    public function enrollment_manager_can_update_placement_in_own_school(): void
    {
        $schoolId = $this->createSchool('SCHOOL-A', 'School A');
        $yearId = $this->createAcademicYear();
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId);

        $newClass = $this->createClassForSchool($schoolId, $yearId, $this->createGradeLevel('G11'));
        $newSection = $this->createSectionForClass((int) $newClass->id);

        $this->actingAsEnrollmentManager(schoolId: $schoolId);

        $this->patchJson('/api/v1/enrollments/'.$enrollment->id, [
            'class_id' => $newClass->id,
            'section_id' => $newSection->id,
        ])->assertOk()
            ->assertJsonPath('data.class_id', $newClass->id)
            ->assertJsonPath('data.section_id', $newSection->id);

        $this->assertDatabaseHas((new EnrollmentRecord)->getTable(), [
            'id' => $enrollment->id,
            'class_id' => $newClass->id,
            'section_id' => $newSection->id,
        ]);
    }

    #[Test]
    public function cannot_update_cancelled_enrollment(): void
    {
        $schoolId = $this->createSchool('SCHOOL-A', 'School A');
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId);
        $enrollment->forceFill(['status' => 2, 'effective_to' => '2026-09-15']);
        $enrollment->save();

        $newClass = $this->createClassForSchool($schoolId, (int) $enrollment->academic_year_id);
        $newSection = $this->createSectionForClass((int) $newClass->id);

        $this->actingAsEnrollmentManager(schoolId: $schoolId);

        $this->patchJson('/api/v1/enrollments/'.$enrollment->id, [
            'class_id' => $newClass->id,
            'section_id' => $newSection->id,
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'enrollment.not_active');
    }
}
