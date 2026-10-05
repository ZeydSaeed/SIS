<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use App\Infrastructure\Jobs\ProcessOutboxJob;
use App\Infrastructure\Persistence\Outbox\EloquentOutboxRepository;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * Workflow phase 4 — the whole journey in one school:
 * accepted application → convert (placement ids + «تسجيل الآن») → bulk enroll →
 * outbox assigns the curriculum's required subjects → curriculum page lists only
 * its grade's students and allows assignment → a prerequisite needs a passing grade.
 */
final class PhaseWorkflowEndToEndJourneyPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function admission_to_curriculum_journey(): void
    {
        $this->withoutVite();
        $schoolId = $this->createSchool('SCH-E2E', 'E2E School');
        $yearId = $this->createAcademicYear('AY-E2E');
        $firstGrade = $this->createGradeLevel('G-E2E-1');
        $secondGrade = $this->createGradeLevel('G-E2E-2');

        $branchId = $this->insertRow('organization', 'branches', [
            'school_id' => $schoolId, 'code' => 'BR-E2E', 'name' => 'الصناعي', 'status' => 1,
        ]);
        $departmentId = $this->insertRow('organization', 'departments', [
            'school_id' => $schoolId, 'branch_id' => $branchId, 'code' => 'DEP-E2E',
            'name' => 'كهرباء', 'department_type' => 1, 'status' => 1,
        ]);
        $firstClass = $this->createClassForSchool($schoolId, $yearId, $firstGrade);
        $firstClass->forceFill(['name' => 'الأول'])->save();
        $firstSection = $this->createSectionForClass((int) $firstClass->id);
        $secondClass = $this->createClassForSchool($schoolId, $yearId, $secondGrade);
        $secondClass->forceFill(['name' => 'الثاني'])->save();

        $basics = $this->createSubject('BAS-E2E');
        $advanced = $this->createSubject('ADV-E2E');
        DB::table(SchemaHelper::qualified('curriculum', 'prerequisites'))->insert([
            'subject_id' => $advanced, 'prerequisite_subject_id' => $basics, 'status' => 1, 'created_at' => now(),
        ]);
        $curriculumId = $this->createCurriculumWithSubjects($schoolId, $yearId, $firstGrade, [$basics]);

        // A student of another grade must not appear on this curriculum.
        $otherGradeStudent = $this->createStudentForSchool($schoolId);
        $this->insertRow('enrollment', 'enrollments', [
            'student_id' => $otherGradeStudent->id, 'academic_year_id' => $yearId, 'school_id' => $schoolId,
            'class_id' => $secondClass->id, 'section_id' => $this->createSectionForClass((int) $secondClass->id)->id,
            'status' => 1, 'effective_from' => '2026-09-01',
        ], withUpdatedAt: true, schoolScoped: $schoolId);

        $this->actingAsWorkflowStaff($schoolId);

        // 1) Admission: accepted application → student with structured placement + «تسجيل الآن».
        $applicationId = $this->insertAcceptedApplication($schoolId, $yearId, $branchId);
        $this->from('/admission')
            ->post("/admission/applications/{$applicationId}/convert")
            ->assertRedirect('/admission');
        $studentId = (int) DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->where('id', $applicationId)->value('student_id');
        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'students'), [
            'id' => $studentId, 'branch_id' => $branchId, 'department_id' => $departmentId, 'grade_level_id' => $firstGrade,
        ]);
        $this->assertSame('/enrollments/create?student_id='.$studentId, session('successAction')['href'] ?? null);

        // 2) Students → enrollment (server bulk command).
        $this->post('/enrollments/bulk', [
            'student_ids' => [$studentId],
            'academic_year_id' => $yearId,
            'class_id' => $firstClass->id,
            'section_id' => $firstSection->id,
            'effective_from' => '2026-09-01',
            'branch_id' => $branchId,
            'department_id' => $departmentId,
        ], ['X-Idempotency-Key' => 'e2e-bulk'])
            ->assertRedirect()
            ->assertSessionHas('bulkEnroll', ['enrolled' => [$studentId], 'skipped' => []]);

        // 3) Enrollment → curriculum: required subject assigned via the outbox.
        (new ProcessOutboxJob)->handle(app(EloquentOutboxRepository::class));
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $enrollmentId = (int) DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('student_id', $studentId)->where('status', 1)->value('id');
        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollment_subjects'), [
            'enrollment_id' => $enrollmentId, 'subject_id' => $basics, 'status' => 1,
        ]);

        // 4) Curriculum page: only this grade's students; assignment allowed.
        $this->get('/curriculum/curricula/'.$curriculumId)
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('curriculum/show')
                ->where('authorization.canAssignEnrollmentSubjects', true)
                ->where('enrollments.pagination.total', 1)
                ->where('enrollments.data.0.id', $enrollmentId));

        // 5) Prerequisite: studying "basics" is not enough — elective outside curriculum still blocked by it.
        $this->post('/curriculum/enrollments/'.$enrollmentId.'/subjects', [
            'subject_id' => $advanced,
            'is_elective' => true,
        ], ['X-Idempotency-Key' => 'e2e-advanced'])
            ->assertSessionHasErrors(['enrollment_subject' => 'enrollment.prerequisite_not_met']);
    }

    private function actingAsWorkflowStaff(int $schoolId): void
    {
        $user = User::factory()->create();
        $seeder = app(SecurityPermissionSeeder::class);
        $seeder->assignRole($user, 'admission_manager', $schoolId);
        $seeder->grantStudentManager($user, $schoolId);
        $seeder->grantEnrollmentManager($user, $schoolId);
        $seeder->grantCurriculumManager($user, $schoolId);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $schoolId]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertRow(
        string $schema,
        string $table,
        array $values,
        bool $withUpdatedAt = true,
        ?int $schoolScoped = null,
    ): int {
        if ($schoolScoped !== null) {
            DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolScoped]);
        }

        return (int) DB::table(SchemaHelper::qualified($schema, $table))->insertGetId($values + [
            'created_at' => now(),
        ] + ($withUpdatedAt ? ['updated_at' => now()] : []));
    }

    private function insertAcceptedApplication(int $schoolId, int $yearId, int $branchId): int
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $periodId = (int) DB::table(SchemaHelper::qualified('admission', 'application_periods'))->insertGetId([
            'academic_year_id' => $yearId,
            'school_id' => $schoolId,
            'name' => 'Period E2E',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'max_applications' => 100,
            'status' => 1,
            'created_at' => now(),
        ]);

        return (int) DB::table(SchemaHelper::qualified('admission', 'applications'))->insertGetId([
            'application_period_id' => $periodId,
            'school_id' => $schoolId,
            'application_number' => 'APP-E2E-1',
            'first_name' => 'Hassan',
            'father_name' => 'Ali',
            'grandfather_name' => 'Kadhim',
            'great_grandfather_name' => 'Nuri',
            'last_name' => 'Journey',
            'mother_name' => 'Sara',
            'maternal_father_name' => 'Omar',
            'maternal_grandfather_name' => 'Zaid',
            'birth_date' => '2010-03-15',
            'birth_place' => 'Baghdad',
            'gender' => 1,
            'target_school_id' => $schoolId,
            'branch_id' => $branchId,
            'department_name' => 'كهرباء',
            'intended_grade_name' => 'الأول',
            'status' => ApplicationStatus::Accepted->value,
            'submitted_at' => now(),
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
