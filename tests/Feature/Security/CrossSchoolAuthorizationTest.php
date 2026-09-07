<?php

namespace Tests\Feature\Security;

use App\Infrastructure\Persistence\Eloquent\SecurityAuditLogRecord;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\TestCase;

class CrossSchoolAuthorizationTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;

    #[Test]
    public function school_a_user_cannot_access_school_b_student_by_id(): void
    {
        $schoolA = $this->createSchool('SCHOOL-A', 'School A');
        $schoolB = $this->createSchool('SCHOOL-B', 'School B');

        $this->actingAsStudentManagerForSchool($schoolA);

        $studentB = $this->createStudentForSchool($schoolB, [
            'student_code' => 'STU-SCH-B-001',
            'first_name' => 'Cross',
            'last_name' => 'School',
            'full_name' => 'Cross School',
        ]);

        $this->getJson("/api/v1/students/{$studentB->id}")
            ->assertForbidden();
    }

    #[Test]
    public function school_a_user_cannot_list_school_b_students(): void
    {
        $schoolA = $this->createSchool('SCHOOL-A', 'School A');
        $schoolB = $this->createSchool('SCHOOL-B', 'School B');

        $this->createStudentForSchool($schoolB, [
            'student_code' => 'STU-SCH-B-002',
            'first_name' => 'Hidden',
            'last_name' => 'Student',
            'full_name' => 'Hidden Student',
            'gender' => 2,
            'birth_date' => '2011-02-02',
        ]);

        $this->actingAsStudentManagerForSchool($schoolA);

        $this->getJson('/api/v1/students')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    #[Test]
    public function cross_school_denial_creates_security_audit_record(): void
    {
        $schoolA = $this->createSchool('SCHOOL-A', 'School A');
        $schoolB = $this->createSchool('SCHOOL-B', 'School B');

        $this->actingAsStudentManagerForSchool($schoolA);

        $studentB = $this->createStudentForSchool($schoolB, [
            'student_code' => 'STU-SCH-B-003',
            'first_name' => 'Audit',
            'last_name' => 'Target',
            'full_name' => 'Audit Target',
            'birth_date' => '2010-03-03',
        ]);

        $this->getJson("/api/v1/students/{$studentB->id}")->assertForbidden();

        $this->assertDatabaseHas((new SecurityAuditLogRecord)->getTable(), [
            'event_id' => 'SEC_IDOR_BLOCKED',
            'result' => 'denied',
            'school_id' => $schoolA,
        ]);
    }

    #[Test]
    public function authorized_user_can_access_own_school_student(): void
    {
        $schoolA = $this->createSchool('SCHOOL-A', 'School A');
        $this->actingAsStudentManagerForSchool($schoolA);

        $create = $this->postJson('/api/v1/students', [
            'first_name' => 'Own',
            'last_name' => 'School',
            'gender' => 1,
            'birth_date' => '2010-04-04',
            'student_code' => 'STU-OWN-001',
        ])->assertCreated();

        $studentId = (int) $create->json('data.id');
        $this->assertSame($schoolA, (int) StudentRecord::query()->find($studentId)?->school_id);

        $this->getJson('/api/v1/students/'.$studentId)
            ->assertOk();
    }
}
