<?php

namespace Tests\Feature\Admission;

use App\Database\SchemaHelper;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\TestCase;

final class AdmissionConvertWorkflowUiTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;

    #[Test]
    public function convert_creates_student_and_stays_on_admission(): void
    {
        $this->withoutVite();
        $schoolId = $this->createSchool('SCHOOL-A', 'School A');
        $user = User::factory()->create();
        $seeder = app(SecurityPermissionSeeder::class);
        $seeder->grantStudentManager($user, $schoolId);
        $seeder->assignRole($user, 'admission_manager', $schoolId);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $schoolId]);

        $yearId = $this->createAcademicYear('AY-ADM-CONVERT');
        $gradeId = $this->createGradeLevel('G-ADM-CV');
        $periodId = $this->insertPeriod($schoolId, $yearId, 'Period Convert');
        $applicationId = $this->insertAcceptedApplication($periodId, $gradeId, $schoolId, 'APP-CV-001');

        $response = $this->from('/admission')->post("/admission/applications/{$applicationId}/convert");

        $response->assertRedirect('/admission');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas(SchemaHelper::qualified('admission', 'applications'), [
            'id' => $applicationId,
            'status' => ApplicationStatus::Converted->value,
        ]);

        $studentId = DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->where('id', $applicationId)
            ->value('student_id');
        $this->assertNotNull($studentId);

        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'students'), [
            'id' => $studentId,
            'school_id' => $schoolId,
            'admitted_academic_year_id' => $yearId,
            'first_name' => 'Hassan',
            'last_name' => 'Convert',
        ]);
    }

    private function insertPeriod(int $schoolId, int $yearId, string $name): int
    {
        return (int) DB::table(SchemaHelper::qualified('admission', 'application_periods'))->insertGetId([
            'academic_year_id' => $yearId,
            'school_id' => $schoolId,
            'name' => $name,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'max_applications' => 100,
            'status' => 1,
            'created_at' => now(),
        ]);
    }

    private function insertAcceptedApplication(
        int $periodId,
        int $gradeId,
        int $schoolId,
        string $number,
    ): int {
        return (int) DB::table(SchemaHelper::qualified('admission', 'applications'))->insertGetId([
            'application_period_id' => $periodId,
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
            'birth_date' => '2012-03-15',
            'birth_place' => 'Baghdad',
            'gender' => 1,
            'target_school_id' => $schoolId,
            'grade_level_id' => $gradeId,
            'intended_grade_name' => 'أول',
            'specialization_id' => null,
            'status' => ApplicationStatus::Accepted->value,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
