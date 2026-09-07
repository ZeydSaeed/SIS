<?php

namespace Tests\Feature\Security;

use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Infrastructure\Persistence\Eloquent\SecurityAuditLogRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\TestCase;

class CrossSchoolEnrollmentTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;

    #[Test]
    public function school_a_user_cannot_view_school_b_enrollment_by_id(): void
    {
        $schoolA = $this->createSchool('SCHOOL-A', 'School A');
        $schoolB = $this->createSchool('SCHOOL-B', 'School B');
        $yearId = $this->createAcademicYear();

        $classB = $this->createClassForSchool($schoolB, $yearId);
        $sectionB = $this->createSectionForClass((int) $classB->id);
        $studentB = $this->createStudentForSchool($schoolB);

        $enrollmentB = new EnrollmentRecord;
        $enrollmentB->forceFill([
            'student_id' => $studentB->id,
            'academic_year_id' => $yearId,
            'school_id' => $schoolB,
            'class_id' => $classB->id,
            'section_id' => $sectionB->id,
            'enrollment_number' => 'ENR-B-001',
            'status' => 1,
            'effective_from' => '2026-09-01',
        ]);
        $enrollmentB->save();

        $this->actingAsEnrollmentManagerForSchool($schoolA);

        $this->getJson('/api/v1/enrollments/'.$enrollmentB->id)
            ->assertForbidden();
    }

    #[Test]
    public function school_a_user_cannot_enroll_with_school_b_class(): void
    {
        $schoolA = $this->createSchool('SCHOOL-A', 'School A');
        $schoolB = $this->createSchool('SCHOOL-B', 'School B');
        $yearId = $this->createAcademicYear();

        $classB = $this->createClassForSchool($schoolB, $yearId);
        $sectionB = $this->createSectionForClass((int) $classB->id);
        $studentA = $this->createStudentForSchool($schoolA);

        $this->actingAsEnrollmentManagerForSchool($schoolA);

        $this->postJson('/api/v1/enrollments', [
            'student_id' => $studentA->id,
            'academic_year_id' => $yearId,
            'class_id' => $classB->id,
            'section_id' => $sectionB->id,
            'effective_from' => '2026-09-01',
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'enrollment.invalid_placement');
    }

    #[Test]
    public function cross_school_enrollment_denial_creates_security_audit_record(): void
    {
        $schoolA = $this->createSchool('SCHOOL-A', 'School A');
        $schoolB = $this->createSchool('SCHOOL-B', 'School B');
        $yearId = $this->createAcademicYear();

        $classB = $this->createClassForSchool($schoolB, $yearId);
        $sectionB = $this->createSectionForClass((int) $classB->id);
        $studentB = $this->createStudentForSchool($schoolB);

        $enrollmentB = new EnrollmentRecord;
        $enrollmentB->forceFill([
            'student_id' => $studentB->id,
            'academic_year_id' => $yearId,
            'school_id' => $schoolB,
            'class_id' => $classB->id,
            'section_id' => $sectionB->id,
            'enrollment_number' => 'ENR-B-002',
            'status' => 1,
            'effective_from' => '2026-09-01',
        ]);
        $enrollmentB->save();

        $this->actingAsEnrollmentManagerForSchool($schoolA);

        $this->getJson('/api/v1/enrollments/'.$enrollmentB->id)->assertForbidden();

        $this->assertDatabaseHas((new SecurityAuditLogRecord)->getTable(), [
            'event_id' => 'SEC_IDOR_BLOCKED',
            'result' => 'denied',
            'school_id' => $schoolA,
        ]);
    }
}
