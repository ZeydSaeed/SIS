<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * «المناهج» (owner, 2026-11-27): a curriculum belongs to a branch, specialization, class and section; each of its
 * subjects has one teacher, or a main teacher and assistants; deleting a curriculum takes its subjects (lines) and
 * their teachers, deleting a subject takes it out of the curricula first.
 */
final class CurriculumScopeTeachersPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function a_curriculum_names_its_branch_specialization_class_and_section(): void
    {
        $ctx = $this->seedSchool('CST-A');

        // The page requires the full place.
        $this->from('/curriculum/curricula')->withHeader('X-Idempotency-Key', 'cst-a-0')
            ->post('/curriculum/curricula', $this->curriculumPayload($ctx, 'A', ['section_id' => null]))
            ->assertSessionHasErrors('section_id');

        $first = $this->createCurriculum($ctx, 'cst-a-1', 'A');
        $row = DB::table('curriculum.curricula')->where('id', $first)->first();
        $this->assertSame([$ctx['branch'], $ctx['department'], $ctx['class'], $ctx['sectionA']], [
            (int) $row->branch_id, (int) $row->department_id, (int) $row->class_id, (int) $row->section_id,
        ]);

        // One active curriculum per branch / specialization / section; section B of the same class is its own.
        $this->from('/curriculum/curricula')->withHeader('X-Idempotency-Key', 'cst-a-2')
            ->post('/curriculum/curricula', $this->curriculumPayload($ctx, 'A'))
            ->assertSessionHasErrors(['curriculum' => 'curriculum.curriculum_exists']);
        $this->createCurriculum($ctx, 'cst-a-3', 'B');

        // Every part must belong to the one above it.
        $i = 0;
        foreach ([
            [['department_id' => $ctx['otherDepartment']], 'curriculum.department_not_in_branch'],
            [['section_id' => $ctx['otherSection']], 'curriculum.section_invalid'],
            [['grade_level_id' => $ctx['otherGrade']], 'curriculum.class_grade_mismatch'],
        ] as [$override, $code]) {
            $this->from('/curriculum/curricula')->withHeader('X-Idempotency-Key', 'cst-a-bad-'.$i++)
                ->post('/curriculum/curricula', $this->curriculumPayload($ctx, 'C', $override))
                ->assertSessionHasErrors(['curriculum' => $code]);
        }

        // The database refuses an inconsistent place too (trigger curricula_scope_check).
        try {
            DB::transaction(fn () => DB::table('curriculum.curricula')->where('id', $first)->update(['section_id' => $ctx['otherSection']]));
            $this->fail('section of another class accepted');
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0] ?? null);
        }

        $this->actingAs($ctx['user'])->withSession(['current_school_id' => $ctx['school']])
            ->get('/curriculum/curricula?academic_year_id='.$ctx['year'])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('curriculum/curricula')
                ->where('curricula.data.0.branch_id', $ctx['branch'])
                ->where('curricula.data.0.class_id', $ctx['class'])
                ->where('curricula.data.0.section_id', $ctx['sectionA'])
                ->where('curricula.data.0.is_complete', false)
                ->has('filterOptions.sections'));
    }

    #[Test]
    public function each_subject_gets_one_teacher_or_a_main_teacher_and_assistants(): void
    {
        $ctx = $this->seedSchool('CST-B');
        $curriculumId = $this->createCurriculum($ctx, 'cst-b-c', 'A');
        $line = $this->linkSubject($curriculumId, $ctx['networks'], 'cst-b-l');
        [$main, $helper1, $helper2, $next] = array_map(
            fn (string $code): int => $this->registerTeacher($ctx, $code),
            ['EMP-CSTB-1', 'EMP-CSTB-2', 'EMP-CSTB-3', 'EMP-CSTB-4'],
        );

        $this->assignTeacher($line, $helper1, 2, 'b1')->assertSessionHasErrors(['teacher' => 'teachers.curriculum_assistant_needs_main']);
        $this->assignTeacher($line, $main, 1, 'b2')->assertSessionHas('success', 'flash.curriculum.teacherAssigned');
        $this->assignTeacher($line, $next, 1, 'b3')->assertSessionHasErrors(['teacher' => 'teachers.curriculum_main_exists']);
        $this->assignTeacher($line, $helper1, 2, 'b4')->assertSessionHas('success', 'flash.curriculum.teacherAssigned');
        $this->assignTeacher($line, $helper2, 2, 'b5')->assertSessionHas('success', 'flash.curriculum.teacherAssigned');
        $this->assignTeacher($line, $helper1, 2, 'b6')->assertSessionHasErrors(['teacher' => 'teachers.curriculum_teacher_exists']);

        // The assignment carries the curriculum's place, so grade entry / timetable see it.
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school']]);
        $mainRow = DB::table('teachers.teaching_assignments')->where('teacher_id', $main)->first();
        $this->assertSame([1, $line, $ctx['branch'], $ctx['department'], $ctx['class'], $ctx['sectionA'], $ctx['networks']], [
            (int) $mainRow->role, (int) $mainRow->curriculum_subject_id, (int) $mainRow->branch_id, (int) $mainRow->department_id,
            (int) $mainRow->class_id, (int) $mainRow->section_id, (int) $mainRow->subject_id,
        ]);
        $this->assertTrue(DB::table('teachers.teacher_subjects')->where('teacher_id', $main)->where('subject_id', $ctx['networks'])->exists());

        // The main teacher stays while assistants remain; «تغيير المدرس الرئيسي» replaces him (history kept).
        $this->from('/curriculum/curricula/'.$curriculumId)
            ->post('/curriculum/curriculum-subjects/'.$line.'/teachers/'.$mainRow->id.'/end')
            ->assertSessionHasErrors(['teacher' => 'teachers.curriculum_main_has_assistants']);
        $this->from('/curriculum/curricula/'.$curriculumId)->withHeader('X-Idempotency-Key', 'cst-b-replace')
            ->post('/curriculum/curriculum-subjects/'.$line.'/teachers', ['teacher_id' => $next, 'teacher_role' => 1, 'replace_main' => 1])
            ->assertSessionHas('success', 'flash.curriculum.teacherAssigned');
        $this->assertSame(2, (int) DB::table('teachers.teaching_assignments')->where('id', $mainRow->id)->value('status'));

        $this->actingAs($ctx['user'])->withSession(['current_school_id' => $ctx['school']])
            ->get('/curriculum/curricula/'.$curriculumId)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('curriculum/show')
                ->where('curriculum.is_complete', true)
                ->where('curriculum.staffed_count', 1)
                ->has('linkedSubjects.0.teachers', 3)
                ->where('linkedSubjects.0.teachers.0.teacher_id', $next)
                ->where('linkedSubjects.0.teachers.0.role', 1)
                ->has('teacherOptions', 4));

        // A curriculum with teachers keeps its specialization.
        $this->from('/curriculum/curricula/'.$curriculumId)->withHeader('X-Idempotency-Key', 'cst-b-move')
            ->patch('/curriculum/curricula/'.$curriculumId, ['department_id' => $ctx['secondDepartment']])
            ->assertSessionHasErrors(['curriculum' => 'curriculum.scope_locked_by_teachers']);

        // An assistant can leave.
        $helperRow = (int) DB::table('teachers.teaching_assignments')->where('teacher_id', $helper2)->value('id');
        $this->from('/curriculum/curricula/'.$curriculumId)
            ->post('/curriculum/curriculum-subjects/'.$line.'/teachers/'.$helperRow.'/end')
            ->assertSessionHas('success', 'flash.curriculum.teacherRemoved');
    }

    #[Test]
    public function deleting_a_curriculum_takes_its_subjects_and_their_teachers(): void
    {
        $ctx = $this->seedSchool('CST-C');
        $curriculumId = $this->createCurriculum($ctx, 'cst-c-c', 'A');
        $lineA = $this->linkSubject($curriculumId, $ctx['networks'], 'cst-c-l1');
        $lineB = $this->linkSubject($curriculumId, $ctx['security'], 'cst-c-l2');
        $teacher = $this->registerTeacher($ctx, 'EMP-CSTC-1');
        $this->assignTeacher($lineA, $teacher, 1, 'c1')->assertSessionHas('success');
        $this->assignTeacher($lineB, $teacher, 1, 'c2')->assertSessionHas('success');

        $this->from('/curriculum/curricula')->post('/curriculum/curricula/'.$curriculumId.'/delete')
            ->assertSessionHas('success', 'flash.curriculum.curriculumDeleted');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school']]);
        $this->assertFalse(DB::table('curriculum.curricula')->where('id', $curriculumId)->exists());
        $this->assertSame(0, DB::table('curriculum.curriculum_subjects')->whereIn('id', [$lineA, $lineB])->count());
        $this->assertSame(0, DB::table('teachers.teaching_assignments')->whereIn('curriculum_subject_id', [$lineA, $lineB])->count());
        // The subjects stay in «المواد»; the «المواد المسندة» rows only this curriculum gave go, so the subjects can
        // be deleted afterwards.
        $this->assertSame(2, DB::table('curriculum.subjects')->whereIn('id', [$ctx['networks'], $ctx['security']])->count());
        $this->assertFalse(DB::table('teachers.teacher_subjects')->where('teacher_id', $teacher)->exists());
        $this->from('/curriculum')->post('/curriculum/subjects/'.$ctx['networks'].'/delete')
            ->assertSessionHas('success', 'flash.curriculum.subjectDeleted');
    }

    #[Test]
    public function a_subject_the_teacher_also_teaches_elsewhere_stays_assigned_to_him(): void
    {
        $ctx = $this->seedSchool('CST-E');
        $curriculumA = $this->createCurriculum($ctx, 'cst-e-a', 'A');
        $curriculumB = $this->createCurriculum($ctx, 'cst-e-b', 'B');
        $lineA = $this->linkSubject($curriculumA, $ctx['networks'], 'cst-e-l1');
        $lineB = $this->linkSubject($curriculumB, $ctx['networks'], 'cst-e-l2');
        $teacher = $this->registerTeacher($ctx, 'EMP-CSTE-1');
        $this->assignTeacher($lineA, $teacher, 1, 'e1')->assertSessionHas('success');
        $this->assignTeacher($lineB, $teacher, 1, 'e2')->assertSessionHas('success');

        $this->from('/curriculum/curricula/'.$curriculumA)->post('/curriculum/curriculum-subjects/'.$lineA.'/delete')
            ->assertSessionHas('success', 'flash.curriculum.subjectLinkDeleted');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school']]);
        $this->assertSame(0, DB::table('teachers.teaching_assignments')->where('curriculum_subject_id', $lineA)->count());
        // Still taught in section B.
        $this->assertTrue(DB::table('teachers.teacher_subjects')->where('teacher_id', $teacher)->where('subject_id', $ctx['networks'])->exists());
        $this->assertSame(1, DB::table('teachers.teaching_assignments')->where('curriculum_subject_id', $lineB)->where('status', 1)->count());
    }

    #[Test]
    public function deleting_a_subject_takes_it_out_of_the_curricula_first(): void
    {
        $ctx = $this->seedSchool('CST-D');
        $curriculumA = $this->createCurriculum($ctx, 'cst-d-a', 'A');
        $curriculumB = $this->createCurriculum($ctx, 'cst-d-b', 'B');
        $lineA = $this->linkSubject($curriculumA, $ctx['networks'], 'cst-d-l1');
        $lineB = $this->linkSubject($curriculumB, $ctx['networks'], 'cst-d-l2');
        $keep = $this->linkSubject($curriculumA, $ctx['security'], 'cst-d-l3');
        $teacher = $this->registerTeacher($ctx, 'EMP-CSTD-1');
        $this->assignTeacher($lineA, $teacher, 1, 'd1')->assertSessionHas('success');
        $this->assignTeacher($keep, $teacher, 1, 'd2')->assertSessionHas('success');

        // Without the confirmed warning the linked subject is refused.
        $this->from('/curriculum')->post('/curriculum/subjects/'.$ctx['networks'].'/delete')
            ->assertSessionHasErrors('delete');
        $this->from('/curriculum')->post('/curriculum/subjects/'.$ctx['networks'].'/delete', ['detach_curricula' => 1])
            ->assertSessionHas('success', 'flash.curriculum.subjectDeleted');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school']]);
        $this->assertFalse(DB::table('curriculum.subjects')->where('id', $ctx['networks'])->exists());
        $this->assertSame(0, DB::table('curriculum.curriculum_subjects')->whereIn('id', [$lineA, $lineB])->count());
        $this->assertSame(0, DB::table('teachers.teaching_assignments')->where('curriculum_subject_id', $lineA)->count());
        $this->assertFalse(DB::table('teachers.teacher_subjects')->where('subject_id', $ctx['networks'])->exists());
        // The curricula and their other subjects / teachers stay.
        $this->assertSame(2, DB::table('curriculum.curricula')->whereIn('id', [$curriculumA, $curriculumB])->count());
        $this->assertSame(1, DB::table('teachers.teaching_assignments')->where('curriculum_subject_id', $keep)->where('status', 1)->count());
    }

    private function assignTeacher(int $line, int $teacher, int $role, string $key): \Illuminate\Testing\TestResponse
    {
        return $this->from('/curriculum/curricula')->withHeader('X-Idempotency-Key', 'cst-assign-'.$key)
            ->post('/curriculum/curriculum-subjects/'.$line.'/teachers', ['teacher_id' => $teacher, 'teacher_role' => $role]);
    }

    /** @param  array<string, mixed>  $ctx */
    private function createCurriculum(array $ctx, string $key, string $section): int
    {
        $this->from('/curriculum/curricula')->withHeader('X-Idempotency-Key', $key)
            ->post('/curriculum/curricula', $this->curriculumPayload($ctx, $section))
            ->assertSessionHas('success', 'flash.curriculum.created');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school']]);

        return (int) DB::table('curriculum.curricula')->where('school_id', $ctx['school'])
            ->where('section_id', $section === 'A' ? $ctx['sectionA'] : $ctx['sectionB'])->value('id');
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function curriculumPayload(array $ctx, string $section, array $override = []): array
    {
        return array_merge([
            'academic_year_id' => $ctx['year'],
            'grade_level_id' => $ctx['grade'],
            'name' => 'منهج '.$section,
            'branch_id' => $ctx['branch'],
            'department_id' => $ctx['department'],
            'class_id' => $ctx['class'],
            'section_id' => $section === 'B' ? $ctx['sectionB'] : $ctx['sectionA'],
        ], $override);
    }

    private function linkSubject(int $curriculumId, int $subjectId, string $key): int
    {
        $this->from('/curriculum/curricula/'.$curriculumId)->withHeader('X-Idempotency-Key', $key)
            ->post('/curriculum/curricula/'.$curriculumId.'/subjects', ['subject_id' => $subjectId, 'theory_hours' => 2, 'practical_hours' => 1, 'is_required' => true])
            ->assertSessionHas('success', 'flash.curriculum.subjectLinked');

        return (int) DB::table('curriculum.curriculum_subjects')->where('curriculum_id', $curriculumId)->where('subject_id', $subjectId)->value('id');
    }

    /** @param  array<string, mixed>  $ctx */
    private function registerTeacher(array $ctx, string $code): int
    {
        $this->from('/teachers')->withHeader('X-Idempotency-Key', 'register-'.$code)
            ->post('/teachers', ['academic_year_id' => $ctx['year'], 'employee_code' => $code, 'first_name' => 'معلم', 'last_name' => $code])
            ->assertSessionHas('success', 'flash.teachers.registered');

        return (int) DB::table('teachers.teachers')->where('employee_code', $code)->value('id');
    }

    /** @return array<string, mixed> */
    private function seedSchool(string $tag): array
    {
        $schoolId = $this->createSchool('SCH-'.$tag, 'School '.$tag);
        $yearId = $this->createAcademicYear('AY-'.$tag);
        $gradeId = $this->createGradeLevel('G-'.$tag);
        $otherGrade = $this->createGradeLevel('G2-'.$tag);
        $branch = $this->createBranch($schoolId, 'الحاسوب وتقنية المعلومات');
        $otherBranch = $this->createBranch($schoolId, 'التجاري');
        $department = $this->createDepartmentForSchool($schoolId, 'شبكات الحاسوب', $branch);
        $secondDepartment = $this->createDepartmentForSchool($schoolId, 'الامن السبراني', $branch);
        $otherDepartment = $this->createDepartmentForSchool($schoolId, 'المحاسبة', $otherBranch);
        $class = (int) $this->createClassForSchool($schoolId, $yearId, $gradeId)->id;
        $otherClass = (int) $this->createClassForSchool($schoolId, $yearId, $otherGrade)->id;
        $sectionA = (int) $this->createSectionForClass($class)->id;
        $sectionB = (int) $this->createSectionForClass($class)->id;
        $otherSection = (int) $this->createSectionForClass($otherClass)->id;

        $user = User::factory()->create();
        // Every role before the first request (permissions are cached per user).
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        app(SecurityPermissionSeeder::class)->grantTeachersManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        return [
            'user' => $user,
            'school' => $schoolId,
            'year' => $yearId,
            'grade' => $gradeId,
            'otherGrade' => $otherGrade,
            'branch' => $branch,
            'department' => $department,
            'secondDepartment' => $secondDepartment,
            'otherDepartment' => $otherDepartment,
            'class' => $class,
            'sectionA' => $sectionA,
            'sectionB' => $sectionB,
            'otherSection' => $otherSection,
            'networks' => $this->createSubject('NET-'.$tag, 'شبكات الحاسوب'),
            'security' => $this->createSubject('SEC-'.$tag, 'أمن المعلومات'),
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
