<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * «المعلمون» (2026-11-28): «تعيين رقم جديد» (generated «الرقم التعريفي»), «قفل» (no sign-in through the link),
 * «الشهادة الحاصل عليها», and the table's teaching columns (part, assistant, «الحصص الإجمالية»).
 */
final class TeachersCodeLockDegreePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    private const CODE_PATTERN = '/^T[ABCDEFGHJKMNPQRSTUVWXYZ2-9]{3}-[ABCDEFGHJKMNPQRSTUVWXYZ2-9]{4}$/';

    #[Test]
    public function new_code_is_generated_in_the_teacher_code_shape_and_replaces_the_old_one(): void
    {
        $ctx = $this->seedSchool('CL-A');
        $this->actingAsTeachersManagerForSchool($ctx['school']);
        $id = $this->registerTeacher($ctx, 'EMP-CL-A1');

        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'cl-a-code')
            ->post('/teachers/'.$id.'/new-code')
            ->assertSessionHas('success', 'flash.teachers.newCodeAssigned');
        $code = (string) DB::table('teachers.teachers')->where('id', $id)->value('employee_code');
        $this->assertMatchesRegularExpression(self::CODE_PATTERN, $code);

        // Same key again: the code already set is kept (idempotent).
        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'cl-a-code')
            ->post('/teachers/'.$id.'/new-code')
            ->assertSessionHas('success', 'flash.teachers.newCodeAssigned');
        $this->assertSame($code, (string) DB::table('teachers.teachers')->where('id', $id)->value('employee_code'));

        // A new key: another code.
        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'cl-a-code-2')
            ->post('/teachers/'.$id.'/new-code')
            ->assertSessionHas('success');
        $second = (string) DB::table('teachers.teachers')->where('id', $id)->value('employee_code');
        $this->assertMatchesRegularExpression(self::CODE_PATTERN, $second);
        $this->assertNotSame($code, $second);

        // The client cannot choose the code here.
        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'cl-a-code-3')
            ->post('/teachers/'.$id.'/new-code', ['employee_code' => 'MINE-1'])
            ->assertSessionHasErrors('employee_code');
    }

    #[Test]
    public function lock_and_unlock_apply_to_the_selection_and_show_on_the_roster(): void
    {
        $ctx = $this->seedSchool('CL-B');
        $this->actingAsTeachersManagerForSchool($ctx['school']);
        $a = $this->registerTeacher($ctx, 'EMP-CL-B1', ['first_name' => 'أحمد']);
        $b = $this->registerTeacher($ctx, 'EMP-CL-B2', ['first_name' => 'باسم']);

        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'cl-b-lock')
            ->post('/teachers/access-lock', ['teacher_ids' => [$a, $b], 'locked' => true])
            ->assertSessionHas('success', 'flash.teachers.accessLocked');
        $this->get('/teachers?academic_year_id='.$ctx['year'])
            ->assertInertia(fn ($page) => $page
                ->where('teachers.0.access_locked', true)
                ->where('teachers.1.access_locked', true)
                ->etc());

        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'cl-b-unlock')
            ->post('/teachers/access-lock', ['teacher_ids' => [$b], 'locked' => false])
            ->assertSessionHas('success', 'flash.teachers.accessUnlocked');
        $this->assertTrue((bool) DB::table('teachers.teachers')->where('id', $a)->value('access_locked'));
        $this->assertFalse((bool) DB::table('teachers.teachers')->where('id', $b)->value('access_locked'));

        // A teacher of another school cannot be locked from here.
        $other = $this->seedSchool('CL-B2');
        DB::table('teachers.teachers')->insert([
            'employee_code' => 'EMP-CL-B-OTHER', 'first_name' => 'س', 'last_name' => 'ص', 'full_name' => 'س ص',
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherId = (int) DB::table('teachers.teachers')->where('employee_code', 'EMP-CL-B-OTHER')->value('id');
        DB::table('teachers.teacher_schools')->insert([
            'teacher_id' => $otherId, 'school_id' => $other['school'], 'academic_year_id' => $other['year'], 'is_primary' => true, 'created_at' => now(),
        ]);
        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'cl-b-foreign')
            ->post('/teachers/access-lock', ['teacher_ids' => [$a, $otherId], 'locked' => false])
            ->assertSessionHasErrors(['teacher' => 'teachers.not_found']);
        $this->assertTrue((bool) DB::table('teachers.teachers')->where('id', $a)->value('access_locked'));
    }

    #[Test]
    public function degree_is_saved_from_the_sheet_and_validated(): void
    {
        $ctx = $this->seedSchool('CL-C');
        $this->actingAsTeachersManagerForSchool($ctx['school']);
        $id = $this->registerTeacher($ctx, 'EMP-CL-C1');

        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'cl-c-degree')
            ->patch('/teachers/'.$id, ['first_name' => 'علي', 'last_name' => 'حسن', 'academic_year_id' => $ctx['year'], 'degree' => 5])
            ->assertSessionHas('success', 'flash.teachers.updated');
        $this->assertSame(5, (int) DB::table('teachers.teachers')->where('id', $id)->value('degree'));

        // Without the field the stored degree is kept.
        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'cl-c-keep')
            ->patch('/teachers/'.$id, ['first_name' => 'علي', 'last_name' => 'حسن'])
            ->assertSessionHas('success');
        $this->assertSame(5, (int) DB::table('teachers.teachers')->where('id', $id)->value('degree'));

        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'cl-c-bad')
            ->patch('/teachers/'.$id, ['first_name' => 'علي', 'last_name' => 'حسن', 'degree' => 9])
            ->assertSessionHasErrors('degree');

        $this->get('/teachers?academic_year_id='.$ctx['year'])
            ->assertInertia(fn ($page) => $page->where('teachers.0.degree', 5)->etc());
    }

    #[Test]
    public function roster_shows_the_assistant_and_total_hours_of_each_teaching(): void
    {
        $ctx = $this->seedSchool('CL-D');
        $this->actingAsTeachersManagerForSchool($ctx['school']);
        $main = $this->registerTeacher($ctx, 'EMP-CL-D1', ['first_name' => 'أحمد', 'last_name' => 'كريم']);
        $assistant = $this->registerTeacher($ctx, 'EMP-CL-D2', ['first_name' => 'باسم', 'last_name' => 'علي']);
        DB::table(SchemaHelper::qualified('curriculum', 'subject_types'))
            ->where('subject_id', $ctx['networks'])->update(['hours' => 3]);

        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'cl-d-main')
            ->post('/teachers/'.$main.'/assignments', [
                'academic_year_id' => $ctx['year'], 'subject_id' => $ctx['networks'], 'branch_id' => $ctx['branch'],
                'department_id' => $ctx['department'], 'class_id' => $ctx['class'], 'section_id' => $ctx['section'],
            ])
            ->assertSessionHas('success', 'flash.teachers.assignmentAdded');
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school']]);
        DB::table('teachers.teaching_assignments')->insert([
            'teacher_id' => $assistant, 'school_id' => $ctx['school'], 'academic_year_id' => $ctx['year'],
            'subject_id' => $ctx['networks'], 'branch_id' => $ctx['branch'], 'department_id' => $ctx['department'],
            'class_id' => $ctx['class'], 'section_id' => $ctx['section'], 'role' => 2, 'status' => 1,
            'effective_from' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get('/teachers?academic_year_id='.$ctx['year'])
            ->assertInertia(fn ($page) => $page
                ->where('teachers.0.id', $main)
                ->where('teachers.0.assignments.0.role', 1)
                ->where('teachers.0.assignments.0.hours', 3)
                ->where('teachers.0.assignments.0.assistant_names', ['باسم علي'])
                ->where('teachers.0.total_hours', 3)
                ->where('teachers.1.id', $assistant)
                ->where('teachers.1.assignments.0.role', 2)
                ->where('teachers.1.assignments.0.assistant_names', [])
                ->where('teachers.1.total_hours', 3)
                ->etc());
    }

    #[Test]
    public function viewer_cannot_assign_codes_or_lock(): void
    {
        $ctx = $this->seedSchool('CL-E');
        $this->actingAsTeachersViewerForSchool($ctx['school']);

        $this->withHeader('X-Idempotency-Key', 'cl-e-code')->post('/teachers/1/new-code')->assertForbidden();
        $this->withHeader('X-Idempotency-Key', 'cl-e-lock')
            ->post('/teachers/access-lock', ['teacher_ids' => [1], 'locked' => true])
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
     * @return array{school:int, year:int, branch:int, department:int, class:int, section:int, networks:int}
     */
    private function seedSchool(string $tag): array
    {
        $schoolId = $this->createSchool('SCH-'.$tag, 'School '.$tag);
        $yearId = $this->createAcademicYear('AY-'.$tag);
        $gradeId = $this->createGradeLevel('G-'.$tag);
        $branchId = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
            'school_id' => $schoolId, 'code' => 'BR-'.$tag, 'name' => 'الحاسوب وتقنية المعلومات',
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $departmentId = $this->createDepartmentForSchool($schoolId, 'شبكات الحاسوب', $branchId);
        $networks = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'NET-'.$tag, 'name' => 'شبكات الحاسوب', 'name_en' => 'NET-'.$tag, 'subject_type' => 1,
            'credit_hours' => 3, 'max_grade' => 100, 'pass_grade' => 50, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->createCurriculumWithSubjects($schoolId, $yearId, $gradeId, [$networks], null, $departmentId);
        $class = $this->createClassForSchool($schoolId, $yearId, $gradeId);
        $section = $this->createSectionForClass((int) $class->id);

        return [
            'school' => $schoolId, 'year' => $yearId, 'branch' => $branchId, 'department' => $departmentId,
            'class' => (int) $class->id, 'section' => (int) $section->id, 'networks' => $networks,
        ];
    }
}
