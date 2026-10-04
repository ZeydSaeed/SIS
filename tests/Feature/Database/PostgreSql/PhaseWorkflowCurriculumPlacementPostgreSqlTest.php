<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * Enrollment ↔ curriculum linkage and placement integrity:
 * - required subjects must belong to the governing curriculum (year + grade [+ department]);
 * - electives may come from outside it;
 * - class/section capacity is enforced;
 * - branch must belong to the school.
 */
final class PhaseWorkflowCurriculumPlacementPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function required_subject_must_belong_to_governing_curriculum_but_electives_are_free(): void
    {
        $schoolId = $this->createSchool('SCH-WFC1', 'WFC1 School');
        $yearId = $this->createAcademicYear('AY-WFC1');
        $class = $this->createClassForSchool($schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);
        $student = $this->createStudentForSchool($schoolId);

        $inCurriculum = $this->createSubject('IN-WFC1');
        $outside = $this->createSubject('OUT-WFC1');
        $this->createCurriculumWithSubjects($schoolId, $yearId, (int) $class->grade_level_id, [$inCurriculum]);

        $this->actAsEnrollmentManager($schoolId);
        $enrollmentId = $this->enroll((int) $student->id, $yearId, (int) $class->id, (int) $section->id)
            ->assertCreated()
            ->json('data.id');

        $this->assignSubject($enrollmentId, $outside, 'wfc1-out-required')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'enrollment.subject_not_in_curriculum');

        $this->assignSubject($enrollmentId, $inCurriculum, 'wfc1-in-required')->assertCreated();
        $this->assignSubject($enrollmentId, $outside, 'wfc1-out-elective', true)->assertCreated();
    }

    #[Test]
    public function department_curriculum_only_governs_enrollments_of_its_department(): void
    {
        $schoolId = $this->createSchool('SCH-WFC2', 'WFC2 School');
        $yearId = $this->createAcademicYear('AY-WFC2');
        $class = $this->createClassForSchool($schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);

        $itDept = $this->createDepartment($schoolId, 'IT-WFC2');
        $otherDept = $this->createDepartment($schoolId, 'ELEC-WFC2');
        $subject = $this->createSubject('NET-WFC2');
        $this->createCurriculumWithSubjects($schoolId, $yearId, (int) $class->grade_level_id, [$subject], departmentId: $itDept);

        $this->actAsEnrollmentManager($schoolId);
        $itEnrollment = $this->enroll(
            (int) $this->createStudentForSchool($schoolId)->id,
            $yearId,
            (int) $class->id,
            (int) $section->id,
            ['department_id' => $itDept],
        )->assertCreated()->json('data.id');
        $otherEnrollment = $this->enroll(
            (int) $this->createStudentForSchool($schoolId)->id,
            $yearId,
            (int) $class->id,
            (int) $section->id,
            ['department_id' => $otherDept],
        )->assertCreated()->json('data.id');

        $this->assignSubject($itEnrollment, $subject, 'wfc2-it')->assertCreated();
        $this->assignSubject($otherEnrollment, $subject, 'wfc2-other')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'enrollment.subject_not_in_curriculum');
    }

    #[Test]
    public function enrollment_is_rejected_when_section_capacity_is_reached(): void
    {
        $schoolId = $this->createSchool('SCH-WFC3', 'WFC3 School');
        $yearId = $this->createAcademicYear('AY-WFC3');
        $class = $this->createClassForSchool($schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);
        $section->forceFill(['capacity' => 1])->save();

        $this->actAsEnrollmentManager($schoolId);
        $this->enroll((int) $this->createStudentForSchool($schoolId)->id, $yearId, (int) $class->id, (int) $section->id)
            ->assertCreated();

        $second = $this->createStudentForSchool($schoolId);
        $this->enroll((int) $second->id, $yearId, (int) $class->id, (int) $section->id)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'enrollment.capacity_reached');

        $this->assertDatabaseMissing(SchemaHelper::qualified('enrollment', 'enrollments'), [
            'student_id' => $second->id,
        ]);
    }

    #[Test]
    public function enrollment_rejects_branch_of_another_school(): void
    {
        $schoolId = $this->createSchool('SCH-WFC4', 'WFC4 School');
        $otherSchoolId = $this->createSchool('SCH-WFC4B', 'WFC4 Other');
        $yearId = $this->createAcademicYear('AY-WFC4');
        $class = $this->createClassForSchool($schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);
        $foreignBranch = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
            'school_id' => $otherSchoolId,
            'code' => 'BR-WFC4',
            'name' => 'Foreign branch',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actAsEnrollmentManager($schoolId);
        $this->enroll(
            (int) $this->createStudentForSchool($schoolId)->id,
            $yearId,
            (int) $class->id,
            (int) $section->id,
            ['branch_id' => $foreignBranch],
        )
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'enrollment.invalid_placement');
    }

    private function actAsEnrollmentManager(int $schoolId): void
    {
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantEnrollmentManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);
    }

    /**
     * @param  array<string, int>  $extra
     */
    private function enroll(int $studentId, int $yearId, int $classId, int $sectionId, array $extra = []): TestResponse
    {
        return $this->postJson('/api/v1/enrollments', array_merge([
            'student_id' => $studentId,
            'academic_year_id' => $yearId,
            'class_id' => $classId,
            'section_id' => $sectionId,
            'effective_from' => '2026-09-01',
        ], $extra));
    }

    private function assignSubject(int $enrollmentId, int $subjectId, string $key, bool $elective = false): TestResponse
    {
        return $this->postJson('/api/v1/enrollments/'.$enrollmentId.'/subjects', [
            'subject_id' => $subjectId,
            'is_elective' => $elective,
        ], ['X-Idempotency-Key' => $key]);
    }

    private function createDepartment(int $schoolId, string $code): int
    {
        return (int) DB::table(SchemaHelper::qualified('organization', 'departments'))->insertGetId([
            'school_id' => $schoolId,
            'code' => $code,
            'name' => 'Department '.$code,
            'department_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSubject(string $code): int
    {
        return (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => $code,
            'name' => 'Subject '.$code,
            'name_en' => 'Subject '.$code,
            'subject_type' => 1,
            'credit_hours' => 3,
            'max_grade' => 100,
            'pass_grade' => 50,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
