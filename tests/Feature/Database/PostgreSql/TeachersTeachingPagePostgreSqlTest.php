<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/** «المعلمون» table — names, نوع التعيين, teaching assignments (branch › department · class / section), bulk actions. */
final class TeachersTeachingPagePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function teacher_teaches_a_subject_of_the_department_curriculum_and_the_table_shows_where(): void
    {
        $ctx = $this->seedSchool('TT-A');
        $this->actingAsTeachersManagerForSchool($ctx['school']);
        $teacherId = $this->registerTeacher($ctx, 'EMP-TT-A1', ['first_name' => 'زيد', 'father_name' => 'سعيد', 'grandfather_name' => 'محمد', 'last_name' => 'الجبوري', 'employment_type' => 1]);

        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'tt-a-assign')
            ->post('/teachers/'.$teacherId.'/assignments', [
                'academic_year_id' => $ctx['year'],
                'subject_id' => $ctx['networks'],
                'branch_id' => $ctx['branch'],
                'department_id' => $ctx['department'],
                'class_id' => $ctx['class'],
                'section_id' => $ctx['section'],
            ])
            ->assertSessionHas('success', 'flash.teachers.assignmentAdded');

        $this->get('/teachers?academic_year_id='.$ctx['year'])
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('teachers/index')
                ->where('teachers.0.full_name', 'زيد سعيد محمد الجبوري')
                ->where('teachers.0.employment_type', 1)
                ->where('teachers.0.subject_ids', [$ctx['networks']])
                ->where('teachers.0.assignments.0.subject_name', 'شبكات الحاسوب')
                ->where('teachers.0.assignments.0.branch_name', 'الحاسوب وتقنية المعلومات')
                ->where('teachers.0.assignments.0.department_name', 'شبكات الحاسوب')
                ->where('teachers.0.assignments.0.section_id', $ctx['section'])
                ->where('branches.0.id', $ctx['branch'])
                ->where('classes.0.sections.0.id', $ctx['section'])
                ->where('curriculumSubjects.by_department.'.$ctx['department'], [$ctx['networks']])
                ->etc());

        // Same assignment again → rejected; ending it keeps history (no delete).
        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'tt-a-assign-dup')
            ->post('/teachers/'.$teacherId.'/assignments', [
                'academic_year_id' => $ctx['year'], 'subject_id' => $ctx['networks'], 'branch_id' => $ctx['branch'],
                'department_id' => $ctx['department'], 'class_id' => $ctx['class'], 'section_id' => $ctx['section'],
            ])
            ->assertSessionHasErrors(['teacher' => 'teachers.assignment_exists']);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school']]);
        $assignmentId = (int) DB::table('teachers.teaching_assignments')->where('teacher_id', $teacherId)->value('id');
        $this->from('/teachers')
            ->post('/teachers/'.$teacherId.'/assignments/'.$assignmentId.'/end')
            ->assertSessionHas('success', 'flash.teachers.assignmentEnded');
        $this->assertSame(2, (int) DB::table('teachers.teaching_assignments')->where('id', $assignmentId)->value('status'));

        $this->get('/teachers?academic_year_id='.$ctx['year'])
            ->assertInertia(fn ($page) => $page->where('teachers.0.assignments', [])->etc());
    }

    #[Test]
    public function placement_and_curriculum_rules_are_enforced(): void
    {
        $ctx = $this->seedSchool('TT-B');
        $this->actingAsTeachersManagerForSchool($ctx['school']);
        $teacherId = $this->registerTeacher($ctx, 'EMP-TT-B1');
        $otherBranch = $this->createBranch($ctx['school'], 'التجاري');

        $cases = [
            // Subject outside the department curriculum.
            [['subject_id' => $ctx['accounting'], 'branch_id' => $ctx['branch'], 'department_id' => $ctx['department']], 'teachers.assignment_subject_not_in_curriculum'],
            // Department of another branch.
            [['subject_id' => $ctx['networks'], 'branch_id' => $otherBranch, 'department_id' => $ctx['department']], 'teachers.assignment_department_invalid'],
            // Section without its class.
            [['subject_id' => $ctx['networks'], 'branch_id' => $ctx['branch'], 'department_id' => $ctx['department'], 'section_id' => $ctx['section']], 'teachers.assignment_section_needs_class'],
        ];
        foreach ($cases as $i => [$payload, $code]) {
            $this->from('/teachers')
                ->withHeader('X-Idempotency-Key', 'tt-b-'.$i)
                ->post('/teachers/'.$teacherId.'/assignments', ['academic_year_id' => $ctx['year']] + $payload)
                ->assertSessionHasErrors(['teacher' => $code]);
        }

        // Branch-wide assignment (no department): the subject belongs to a curriculum of one of the branch departments.
        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'tt-b-branch-wide')
            ->post('/teachers/'.$teacherId.'/assignments', ['academic_year_id' => $ctx['year'], 'subject_id' => $ctx['networks'], 'branch_id' => $ctx['branch']])
            ->assertSessionHas('success', 'flash.teachers.assignmentAdded');
    }

    #[Test]
    public function bulk_status_and_employment_type_apply_to_every_selected_teacher(): void
    {
        $ctx = $this->seedSchool('TT-C');
        $this->actingAsTeachersManagerForSchool($ctx['school']);
        $a = $this->registerTeacher($ctx, 'EMP-TT-C1');
        $b = $this->registerTeacher($ctx, 'EMP-TT-C2');

        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'tt-c-type')
            ->post('/teachers/bulk-employment-type', ['teacher_ids' => [$a, $b], 'academic_year_id' => $ctx['year'], 'employment_type' => 4])
            ->assertSessionHas('success', 'flash.teachers.employmentTypeChanged');
        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'tt-c-status')
            ->post('/teachers/bulk-status', ['teacher_ids' => [$a, $b], 'teacher_status' => 2])
            ->assertSessionHas('success', 'flash.teachers.statusChanged');

        $this->get('/teachers?academic_year_id='.$ctx['year'])
            ->assertInertia(fn ($page) => $page
                ->where('teachers.0.employment_type', 4)
                ->where('teachers.1.employment_type', 4)
                ->where('teachers.0.status', 2)
                ->where('teachers.1.status', 2)
                ->etc());

        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'tt-c-bad-type')
            ->post('/teachers/bulk-employment-type', ['teacher_ids' => [$a], 'academic_year_id' => $ctx['year'], 'employment_type' => 9])
            ->assertSessionHasErrors('employment_type');
    }

    #[Test]
    public function viewer_cannot_change_teachers(): void
    {
        $ctx = $this->seedSchool('TT-D');
        $this->actingAsTeachersViewerForSchool($ctx['school']);

        $this->withHeader('X-Idempotency-Key', 'tt-d-status')
            ->post('/teachers/bulk-status', ['teacher_ids' => [1], 'teacher_status' => 2])
            ->assertForbidden();
        $this->withHeader('X-Idempotency-Key', 'tt-d-assign')
            ->post('/teachers/1/assignments', ['academic_year_id' => $ctx['year'], 'subject_id' => $ctx['networks'], 'branch_id' => $ctx['branch']])
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function registerTeacher(array $ctx, string $code, array $overrides = []): int
    {
        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'register-'.$code)
            ->post('/teachers', $overrides + [
                'academic_year_id' => $ctx['year'],
                'employee_code' => $code,
                'first_name' => 'علي',
                'last_name' => 'حسن',
            ])
            ->assertSessionHas('success', 'flash.teachers.registered');

        return (int) DB::table('teachers.teachers')->where('employee_code', $code)->value('id');
    }

    /**
     * @return array{school:int, year:int, branch:int, department:int, class:int, section:int, networks:int, accounting:int}
     */
    private function seedSchool(string $tag): array
    {
        $schoolId = $this->createSchool('SCH-'.$tag, 'School '.$tag);
        $yearId = $this->createAcademicYear('AY-'.$tag);
        $gradeId = $this->createGradeLevel('G-'.$tag);
        $branchId = $this->createBranch($schoolId, 'الحاسوب وتقنية المعلومات');
        $departmentId = $this->createDepartmentForSchool($schoolId, 'شبكات الحاسوب', $branchId);
        $networks = $this->createSubject('NET-'.$tag, 'شبكات الحاسوب');
        $accounting = $this->createSubject('ACC-'.$tag, 'محاسبة الشركات');
        $this->createCurriculumWithSubjects($schoolId, $yearId, $gradeId, [$networks], null, $departmentId);
        $class = $this->createClassForSchool($schoolId, $yearId, $gradeId);
        $section = $this->createSectionForClass((int) $class->id);

        return [
            'school' => $schoolId,
            'year' => $yearId,
            'branch' => $branchId,
            'department' => $departmentId,
            'class' => (int) $class->id,
            'section' => (int) $section->id,
            'networks' => $networks,
            'accounting' => $accounting,
        ];
    }

    private function createBranch(int $schoolId, string $name): int
    {
        return (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
            'school_id' => $schoolId,
            'code' => 'BR-'.$schoolId.'-'.substr(md5($name), 0, 6),
            'name' => $name,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSubject(string $code, string $name): int
    {
        return (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => $code,
            'name' => $name,
            'name_en' => $code,
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
