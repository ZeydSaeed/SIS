<?php

namespace Tests\Feature\Database\PostgreSql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class EnrollmentDateAndCurriculumWarningPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    /** @return array{school:int, year:int, class:int, section:int, department:int, branch:int} */
    private function fixture(): array
    {
        $school = $this->createSchool('SCH-ENR-D', 'Enrollment dates');
        $year = $this->createAcademicYear();
        $this->actingAsEnrollmentManagerWeb(null, $school);
        $class = $this->createClassForSchool($school, $year);
        $section = $this->createSectionForClass((int) $class->id);
        $branch = (int) DB::table('organization.branches')->insertGetId([
            'school_id' => $school, 'code' => 'BR-ENR', 'name' => 'Branch', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $department = $this->createDepartmentForSchool($school, 'Dept', $branch);

        return ['school' => $school, 'year' => $year, 'class' => (int) $class->id, 'section' => (int) $section->id, 'department' => $department, 'branch' => $branch];
    }

    private function enroll(array $f, int $studentId, string $date, string $key): \Illuminate\Testing\TestResponse
    {
        return $this->from('/enrollments')->withHeader('X-Idempotency-Key', $key)->post('/enrollments', [
            'student_id' => $studentId, 'academic_year_id' => $f['year'], 'class_id' => $f['class'], 'section_id' => $f['section'],
            'branch_id' => $f['branch'], 'department_id' => $f['department'], 'effective_from' => $date,
        ]);
    }

    #[Test]
    public function enrollment_must_start_inside_the_academic_year(): void
    {
        $f = $this->fixture();
        $student = $this->createStudentForSchool($f['school']);

        // The year runs 2026-09-01 → 2027-06-30.
        $this->enroll($f, (int) $student->id, '1999-01-01', 'enr-date-1')->assertSessionHas('error', 'enrollment.date_outside_year');
        $this->enroll($f, (int) $student->id, '2027-07-01', 'enr-date-2')->assertSessionHas('error', 'enrollment.date_outside_year');
        $this->assertSame(0, DB::table('enrollment.enrollments')->where('student_id', $student->id)->count());

        $this->enroll($f, (int) $student->id, '2026-09-01', 'enr-date-3')->assertSessionMissing('error');
        $this->assertSame(1, DB::table('enrollment.enrollments')->where('student_id', $student->id)->count());
    }

    #[Test]
    public function placement_without_a_curriculum_succeeds_with_a_warning_and_one_with_a_curriculum_does_not(): void
    {
        $f = $this->fixture();
        $first = $this->createStudentForSchool($f['school']);
        $second = $this->createStudentForSchool($f['school']);

        $this->enroll($f, (int) $first->id, '2026-09-01', 'enr-warn-1')
            ->assertSessionMissing('error')
            ->assertSessionHas('toast', ['type' => 'warning', 'message' => 'enrollment.no_curriculum_for_placement']);
        $this->assertSame(1, DB::table('enrollment.enrollments')->where('student_id', $first->id)->count());

        $gradeLevel = (int) DB::table('enrollment.classes')->where('id', $f['class'])->value('grade_level_id');
        DB::table('curriculum.curricula')->insert([
            'school_id' => $f['school'], 'academic_year_id' => $f['year'], 'grade_level_id' => $gradeLevel,
            'department_id' => $f['department'], 'name' => 'C', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->enroll($f, (int) $second->id, '2026-09-01', 'enr-warn-2')
            ->assertSessionMissing('error')
            ->assertSessionMissing('toast');
    }
}
