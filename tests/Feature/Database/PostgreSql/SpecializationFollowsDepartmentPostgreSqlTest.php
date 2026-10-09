<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Domain\Admission\Data\UpdateApplicationDraftData;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/** The department is the single source of the placement; the specialization is its 1:1 mirror and follows it. */
final class SpecializationFollowsDepartmentPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    /** @return array{school:int, branch:int, dept:int, spec:int, other:int} */
    private function fixture(): array
    {
        $school = $this->createSchool('SCH-SPF', 'Spec follows');
        $branch = (int) DB::table('organization.branches')->insertGetId([
            'school_id' => $school, 'code' => 'BR-SPF', 'name' => 'Industrial', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $dept = $this->createDepartmentForSchool($school, 'Electrical', $branch);
        $otherDept = $this->createDepartmentForSchool($school, 'Mechanical', $branch);
        $mirror = fn (int $d, string $code, string $name): int => (int) DB::table('vocational.specializations')->insertGetId([
            'school_id' => $school, 'department_id' => $d, 'code' => $code, 'name' => $name, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['school' => $school, 'branch' => $branch, 'dept' => $dept, 'spec' => $mirror($dept, 'SPC-E', 'Electrical'), 'other' => $mirror($otherDept, 'SPC-M', 'Mechanical')];
    }

    #[Test]
    public function a_curriculum_gets_the_specialization_of_its_department_and_follows_a_department_change(): void
    {
        $f = $this->fixture();
        $year = $this->createAcademicYear();
        $grade = $this->createGradeLevel();
        $repo = app(CurriculumRepositoryInterface::class);

        $id = $repo->create($f['school'], $year, $grade, 'C', null, now()->toIso8601String(), $f['dept']);
        $this->assertSame($f['spec'], (int) DB::table('curriculum.curricula')->where('id', $id)->value('specialization_id'));

        $otherDept = (int) DB::table('vocational.specializations')->where('id', $f['other'])->value('department_id');
        $repo->updateActive($f['school'], $id, ['department_id' => $otherDept], now()->toIso8601String());
        $this->assertSame($f['other'], (int) DB::table('curriculum.curricula')->where('id', $id)->value('specialization_id'));
    }

    #[Test]
    public function an_application_specialization_follows_its_department_over_what_the_form_sent(): void
    {
        $f = $this->fixture();
        $year = $this->createAcademicYear();
        $period = (int) DB::table('admission.application_periods')->insertGetId([
            'academic_year_id' => $year, 'name' => 'P', 'start_date' => now()->subDay(), 'end_date' => now()->addMonth(), 'max_applications' => 10, 'status' => 1, 'created_at' => now(),
        ]);
        $app = (int) DB::table('admission.applications')->insertGetId([
            'application_period_id' => $period, 'school_id' => $f['school'], 'application_number' => 'SPF-1', 'first_name' => 'Ali', 'last_name' => 'X',
            'birth_date' => '2010-01-01', 'gender' => 1, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // The form sends a stale specialization (the mechanical one) with the electrical department: the mirror wins.
        app(AdmissionRepositoryInterface::class)->updateDraft(new UpdateApplicationDraftData(
            applicationId: $app, notes: null, reviewedAt: null, branchId: $f['branch'], branchName: 'Industrial', departmentName: 'Electrical',
            specializationId: $f['other'], specializationName: 'Mechanical', updatePlacement: true,
        ));

        $row = DB::table('admission.applications')->where('id', $app)->first();
        $this->assertSame($f['dept'], (int) $row->department_id);
        $this->assertSame($f['spec'], (int) $row->specialization_id);
        $this->assertSame('Electrical', $row->specialization_name);
    }
}
