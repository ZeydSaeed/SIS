<?php

namespace Tests\Feature\Security;

use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Infrastructure\Persistence\Eloquent\SecurityAuditLogRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\TestCase;

class EnrollmentAuditPersistenceTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;

    #[Test]
    public function successful_enrollment_creates_security_audit_record(): void
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
        ])->assertCreated();

        $enrollmentId = (int) $response->json('data.id');

        $this->assertDatabaseHas((new SecurityAuditLogRecord)->getTable(), [
            'event_id' => 'SEC_ENROLLMENT_DATA_MODIFIED',
            'action' => 'enrollments.store',
            'result' => 'created',
            'school_id' => $schoolId,
        ]);

        $this->assertDatabaseHas((new EnrollmentRecord)->getTable(), [
            'id' => $enrollmentId,
            'school_id' => $schoolId,
        ]);
    }
}
