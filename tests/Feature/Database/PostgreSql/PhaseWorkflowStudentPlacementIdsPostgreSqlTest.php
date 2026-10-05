<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * Workflow phase 3:
 * - A1: converting an application stores structured placement ids on the student;
 * - the backfill command fills missing ids for existing students;
 * - convert offers «تسجيل الآن» (successAction → enrollment create) except for multi-select batches.
 */
final class PhaseWorkflowStudentPlacementIdsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function convert_stores_placement_ids_and_offers_enroll_now(): void
    {
        [$schoolId, $yearId, $branchId, $departmentId, $gradeLevelId] = $this->placementCatalog('WFP1');
        $this->actingAsAdmissionManager($schoolId);

        $applicationId = $this->insertAcceptedApplication($schoolId, $yearId, $branchId, 'APP-WFP1');

        $this->from('/admission')
            ->post("/admission/applications/{$applicationId}/convert")
            ->assertRedirect('/admission')
            ->assertSessionHas('success', 'flash.admission.convertedToStudent');

        $studentId = (int) DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->where('id', $applicationId)
            ->value('student_id');

        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'students'), [
            'id' => $studentId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'grade_level_id' => $gradeLevelId,
        ]);
        $this->assertSame(
            ['label' => 'workflow.enrollNow', 'href' => '/enrollments/create?student_id='.$studentId],
            session('successAction'),
        );
    }

    #[Test]
    public function batch_convert_does_not_offer_a_single_student_shortcut(): void
    {
        [$schoolId, $yearId, $branchId] = $this->placementCatalog('WFP2');
        $this->actingAsAdmissionManager($schoolId);
        $applicationId = $this->insertAcceptedApplication($schoolId, $yearId, $branchId, 'APP-WFP2');

        $this->from('/admission')
            ->post("/admission/applications/{$applicationId}/convert", ['batch' => 1])
            ->assertRedirect('/admission')
            ->assertSessionHas('success')
            ->assertSessionMissing('successAction');
    }

    #[Test]
    public function backfill_command_is_a_no_op_once_placement_names_are_dropped(): void
    {
        // The student keeps placement as ids only (names dropped 2026-10-05).
        $this->assertFalse(Schema::hasColumn(SchemaHelper::qualified('students', 'students'), 'department_name'));
        $this->assertFalse(Schema::hasColumn(SchemaHelper::qualified('students', 'students'), 'admitted_class_name'));

        $this->artisan('sis:backfill-student-placement-ids')->assertSuccessful();
    }

    /**
     * School with the admission catalog: branch الصناعي → department كهرباء, class الأول (grade level).
     *
     * @return array{0:int, 1:int, 2:int, 3:int, 4:int}
     */
    private function placementCatalog(string $suffix): array
    {
        $schoolId = $this->createSchool('SCH-'.$suffix, $suffix.' School');
        $yearId = $this->createAcademicYear('AY-'.$suffix);
        $gradeLevelId = $this->createGradeLevel('G-'.$suffix);

        $branchId = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
            'school_id' => $schoolId,
            'code' => 'BR-'.$suffix,
            'name' => 'الصناعي',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $departmentId = (int) DB::table(SchemaHelper::qualified('organization', 'departments'))->insertGetId([
            'school_id' => $schoolId,
            'branch_id' => $branchId,
            'code' => 'DEP-'.$suffix,
            'name' => 'كهرباء',
            'department_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $class = $this->createClassForSchool($schoolId, $yearId, $gradeLevelId);
        $class->forceFill(['name' => 'الأول'])->save();

        return [$schoolId, $yearId, $branchId, $departmentId, $gradeLevelId];
    }

    private function actingAsAdmissionManager(int $schoolId): void
    {
        $user = User::factory()->create();
        $seeder = app(SecurityPermissionSeeder::class);
        $seeder->grantStudentManager($user, $schoolId);
        $seeder->assignRole($user, 'admission_manager', $schoolId);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $schoolId]);
    }

    private function insertAcceptedApplication(int $schoolId, int $yearId, int $branchId, string $number): int
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $periodId = (int) DB::table(SchemaHelper::qualified('admission', 'application_periods'))->insertGetId([
            'academic_year_id' => $yearId,
            'school_id' => $schoolId,
            'name' => 'Period '.$number,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'max_applications' => 100,
            'status' => 1,
            'created_at' => now(),
        ]);

        return (int) DB::table(SchemaHelper::qualified('admission', 'applications'))->insertGetId([
            'application_period_id' => $periodId,
            'school_id' => $schoolId,
            'application_number' => $number,
            'first_name' => 'Hassan',
            'father_name' => 'Ali',
            'grandfather_name' => 'Kadhim',
            'great_grandfather_name' => 'Nuri',
            'last_name' => 'Convert',
            'mother_name' => 'Sara',
            'maternal_father_name' => 'Omar',
            'maternal_grandfather_name' => 'Zaid',
            'national_id' => null,
            'birth_date' => '2010-03-15',
            'birth_place' => 'Baghdad',
            'gender' => 1,
            'target_school_id' => $schoolId,
            'branch_id' => $branchId,
            'department_name' => 'كهرباء',
            'intended_grade_name' => 'الأول',
            'specialization_id' => null,
            'status' => ApplicationStatus::Accepted->value,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
