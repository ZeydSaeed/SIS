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
    public function a_curriculum_names_its_branch_specialization_and_class_and_each_teacher_his_section(): void
    {
        $ctx = $this->seedSchool('CST-A');

        // Branch, specialization and class are required; the section is chosen per teacher (optional here).
        $this->from('/curriculum/curricula')->withHeader('X-Idempotency-Key', 'cst-a-0')
            ->post('/curriculum/curricula', $this->curriculumPayload($ctx, 'A', ['class_id' => null]))
            ->assertSessionHasErrors('class_id');
        $first = $this->createClassCurriculum($ctx, 'cst-a-1');
        $row = DB::table('curriculum.curricula')->where('id', $first)->first();
        $this->assertSame([$ctx['branch'], $ctx['department'], $ctx['class']], [
            (int) $row->branch_id, (int) $row->department_id, (int) $row->class_id,
        ]);
        $this->assertNull($row->section_id);

        // One active class-level curriculum per branch / specialization / class.
        $this->from('/curriculum/curricula')->withHeader('X-Idempotency-Key', 'cst-a-2')
            ->post('/curriculum/curricula', $this->curriculumPayload($ctx, 'A', ['section_id' => null]))
            ->assertSessionHasErrors(['curriculum' => 'curriculum.curriculum_exists']);

        // Every part must belong to the one above it.
        $i = 0;
        foreach ([
            [['department_id' => $ctx['otherDepartment']], 'curriculum.department_not_in_branch'],
            [['section_id' => $ctx['otherSection']], 'curriculum.section_invalid'],
            [['grade_level_id' => $ctx['otherGrade']], 'curriculum.class_grade_mismatch'],
        ] as [$override, $code]) {
            $this->from('/curriculum/curricula')->withHeader('X-Idempotency-Key', 'cst-a-bad-'.$i++)
                ->post('/curriculum/curricula', $this->curriculumPayload($ctx, 'B', $override))
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
                ->where('curricula.data.0.is_complete', false)
                ->has('filterOptions.sections')
                ->has('teacherOptions'));
    }

    #[Test]
    public function each_section_gets_its_teachers_several_main_teachers_and_assistants(): void
    {
        $ctx = $this->seedSchool('CST-B');
        $curriculumId = $this->createClassCurriculum($ctx, 'cst-b-c');
        $line = $this->linkSubject($curriculumId, $ctx['networks'], 'cst-b-l');
        [$teacherA, $teacherB, $helper, $second] = array_map(
            fn (string $code): int => $this->registerTeacher($ctx, $code),
            ['EMP-CSTB-1', 'EMP-CSTB-2', 'EMP-CSTB-3', 'EMP-CSTB-4'],
        );

        // Section A: an assistant needs a main teacher of section A first.
        $this->assignTeacher($line, $helper, 2, 'b1', $ctx['sectionA'])->assertSessionHasErrors(['teacher' => 'teachers.curriculum_assistant_needs_main']);
        $this->assignTeacher($line, $teacherA, 1, 'b2', $ctx['sectionA'])->assertSessionHas('success', 'flash.curriculum.teacherAssigned');
        // Several main teachers are allowed; the same teacher twice in the same section is not.
        $this->assignTeacher($line, $second, 1, 'b3', $ctx['sectionA'])->assertSessionHas('success', 'flash.curriculum.teacherAssigned');
        $this->assignTeacher($line, $helper, 2, 'b4', $ctx['sectionA'])->assertSessionHas('success', 'flash.curriculum.teacherAssigned');
        $this->assignTeacher($line, $helper, 2, 'b5', $ctx['sectionA'])->assertSessionHasErrors(['teacher' => 'teachers.curriculum_teacher_exists']);
        // A section of another class is refused.
        $this->assignTeacher($line, $teacherB, 1, 'b6', $ctx['otherSection'])->assertSessionHasErrors(['teacher' => 'teachers.assignment_section_invalid']);

        // Only section A is taught yet: not complete. Section B by another teacher completes it.
        $this->actingAs($ctx['user'])->withSession(['current_school_id' => $ctx['school']])
            ->get('/curriculum/curricula/'.$curriculumId)
            ->assertInertia(fn (Assert $page) => $page->where('curriculum.is_complete', false)->etc());
        $this->assignTeacher($line, $teacherB, 1, 'b7', $ctx['sectionB'])->assertSessionHas('success', 'flash.curriculum.teacherAssigned');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school']]);
        $rowA = DB::table('teachers.teaching_assignments')->where('teacher_id', $teacherA)->first();
        $this->assertSame([1, $line, $ctx['branch'], $ctx['department'], $ctx['class'], $ctx['sectionA'], $ctx['networks']], [
            (int) $rowA->role, (int) $rowA->curriculum_subject_id, (int) $rowA->branch_id, (int) $rowA->department_id,
            (int) $rowA->class_id, (int) $rowA->section_id, (int) $rowA->subject_id,
        ]);

        $this->actingAs($ctx['user'])->withSession(['current_school_id' => $ctx['school']])
            ->get('/curriculum/curricula/'.$curriculumId)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('curriculum/show')
                ->where('curriculum.is_complete', true)
                ->has('linkedSubjects.0.teachers', 4)
                ->has('teacherOptions', 4));

        // One of two main teachers of section A may leave; the last one stays while the assistant remains.
        $this->from('/curriculum/curricula/'.$curriculumId)
            ->post('/curriculum/curriculum-subjects/'.$line.'/teachers/'.$rowA->id.'/end')
            ->assertSessionHas('success', 'flash.curriculum.teacherRemoved');
        $secondRow = (int) DB::table('teachers.teaching_assignments')->where('teacher_id', $second)->value('id');
        $this->from('/curriculum/curricula/'.$curriculumId)
            ->post('/curriculum/curriculum-subjects/'.$line.'/teachers/'.$secondRow.'/end')
            ->assertSessionHasErrors(['teacher' => 'teachers.curriculum_main_has_assistants']);

        // A curriculum with teachers keeps its specialization.
        $this->from('/curriculum/curricula/'.$curriculumId)->withHeader('X-Idempotency-Key', 'cst-b-move')
            ->patch('/curriculum/curricula/'.$curriculumId, ['department_id' => $ctx['secondDepartment']])
            ->assertSessionHasErrors(['curriculum' => 'curriculum.scope_locked_by_teachers']);
    }

    #[Test]
    public function a_curriculum_states_its_number_of_subjects_and_fewer_is_accepted_but_incomplete(): void
    {
        $ctx = $this->seedSchool('CST-H');

        // «عدد المواد» is required on the page.
        $this->from('/curriculum/curricula')->withHeader('X-Idempotency-Key', 'cst-h-0')
            ->post('/curriculum/curricula', $this->curriculumPayload($ctx, 'A', ['section_id' => null, 'planned_subject_count' => null]))
            ->assertSessionHasErrors('planned_subject_count');

        $this->from('/curriculum/curricula')->withHeader('X-Idempotency-Key', 'cst-h-1')
            ->post('/curriculum/curricula', $this->curriculumPayload($ctx, 'A', ['section_id' => null, 'planned_subject_count' => 3]))
            ->assertSessionHas('success', 'flash.curriculum.created');
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school']]);
        $curriculumId = (int) DB::table('curriculum.curricula')->where('school_id', $ctx['school'])->value('id');
        $this->assertSame(3, (int) DB::table('curriculum.curricula')->where('id', $curriculumId)->value('planned_subject_count'));

        // 1 of 3 subjects, with its teacher: accepted, «غير مكتمل», 33%.
        $line = $this->linkSubject($curriculumId, $ctx['networks'], 'cst-h-l1');
        $teacher = $this->registerTeacher($ctx, 'EMP-CSTH-1');
        $this->assignTeacher($line, $teacher, 1, 'h1')->assertSessionHas('success');
        $this->actingAs($ctx['user'])->withSession(['current_school_id' => $ctx['school']])
            ->get('/curriculum/curricula/'.$curriculumId)
            ->assertInertia(fn (Assert $page) => $page
                ->where('curriculum.planned_subject_count', 3)
                ->where('curriculum.subject_count', 1)
                ->where('curriculum.subjects_percent', 33)
                ->where('curriculum.is_complete', false)
                ->etc());

        // The stated number lowered to 1 (edit): complete.
        $this->from('/curriculum/curricula/'.$curriculumId)->withHeader('X-Idempotency-Key', 'cst-h-2')
            ->patch('/curriculum/curricula/'.$curriculumId, ['planned_subject_count' => 1])
            ->assertSessionHas('success', 'flash.curriculum.updated');
        $this->actingAs($ctx['user'])->withSession(['current_school_id' => $ctx['school']])
            ->get('/curriculum/curricula/'.$curriculumId)
            ->assertInertia(fn (Assert $page) => $page
                ->where('curriculum.subjects_percent', 100)
                ->where('curriculum.is_complete', true)
                ->etc());
    }

    #[Test]
    public function one_teacher_may_teach_all_sections(): void
    {
        $ctx = $this->seedSchool('CST-G');
        $curriculumId = $this->createClassCurriculum($ctx, 'cst-g-c');
        $line = $this->linkSubject($curriculumId, $ctx['networks'], 'cst-g-l');
        $teacher = $this->registerTeacher($ctx, 'EMP-CSTG-1');

        // No section = all sections of the class: complete at once.
        $this->assignTeacher($line, $teacher, 1, 'g1')->assertSessionHas('success', 'flash.curriculum.teacherAssigned');
        $this->assignTeacher($line, $teacher, 1, 'g2', $ctx['sectionA'])->assertSessionHasErrors(['teacher' => 'teachers.curriculum_teacher_exists']);
        $this->actingAs($ctx['user'])->withSession(['current_school_id' => $ctx['school']])
            ->get('/curriculum/curricula/'.$curriculumId)
            ->assertInertia(fn (Assert $page) => $page->where('curriculum.is_complete', true)->etc());
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
    public function creating_a_curriculum_assigns_each_part_its_teachers_in_one_step(): void
    {
        $ctx = $this->seedSchool('CST-F');
        // «نظري + عملي»: the trigger adds the primary type (3), the second type is added like the page does.
        DB::table('curriculum.subjects')->where('id', $ctx['networks'])->update(['subject_type' => 3]);
        DB::table('curriculum.subject_types')->insertOrIgnore(['subject_id' => $ctx['networks'], 'subject_type' => 4]);
        DB::table('curriculum.subject_types')->where('subject_id', $ctx['networks'])->where('subject_type', 1)->delete();
        [$theory, $practical, $helper] = array_map(
            fn (string $code): int => $this->registerTeacher($ctx, $code),
            ['EMP-CSTF-1', 'EMP-CSTF-2', 'EMP-CSTF-3'],
        );

        // A refused teacher (not of the school) undoes the whole curriculum.
        $this->from('/curriculum/curricula')->withHeader('X-Idempotency-Key', 'cst-f-bad')
            ->post('/curriculum/curricula', $this->curriculumPayload($ctx, 'A') + [
                'subject_ids' => [$ctx['networks']],
                'teachers' => [['subject_id' => $ctx['networks'], 'subject_type' => 4, 'teacher_id' => 999999, 'teacher_role' => 1]],
            ])
            ->assertSessionHasErrors(['curriculum' => 'teachers.not_in_school_year']);
        $this->assertSame(0, DB::table('curriculum.curricula')->where('school_id', $ctx['school'])->count());

        // نظري → one teacher; عملي → another with an assistant (sent before its main teacher: mains go first).
        $this->from('/curriculum/curricula')->withHeader('X-Idempotency-Key', 'cst-f-ok')
            ->post('/curriculum/curricula', $this->curriculumPayload($ctx, 'A') + [
                'subject_ids' => [$ctx['networks'], $ctx['security']],
                'teachers' => [
                    ['subject_id' => $ctx['networks'], 'subject_type' => 3, 'teacher_id' => $helper, 'teacher_role' => 2],
                    ['subject_id' => $ctx['networks'], 'subject_type' => 4, 'teacher_id' => $theory, 'teacher_role' => 1],
                    ['subject_id' => $ctx['networks'], 'subject_type' => 3, 'teacher_id' => $practical, 'teacher_role' => 1],
                    ['subject_id' => $ctx['security'], 'subject_type' => null, 'teacher_id' => $theory, 'teacher_role' => 1],
                ],
            ])
            ->assertSessionHas('success', 'flash.curriculum.created');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school']]);
        $curriculumId = (int) DB::table('curriculum.curricula')->where('school_id', $ctx['school'])->value('id');
        $line = (int) DB::table('curriculum.curriculum_subjects')->where('curriculum_id', $curriculumId)->where('subject_id', $ctx['networks'])->value('id');
        $parts = DB::table('teachers.teaching_assignments')->where('curriculum_subject_id', $line)->orderBy('teacher_id')
            ->get(['teacher_id', 'role', 'subject_type'])->map(fn ($r) => [(int) $r->teacher_id, (int) $r->role, (int) $r->subject_type])->all();
        $this->assertSame([[$theory, 1, 4], [$practical, 1, 3], [$helper, 2, 3]], $parts);

        $this->actingAs($ctx['user'])->withSession(['current_school_id' => $ctx['school']])
            ->get('/curriculum/curricula/'.$curriculumId)
            ->assertInertia(fn (Assert $page) => $page
                ->where('curriculum.is_complete', true)
                ->where('curriculum.staffed_count', 2)
                ->etc());

        // A whole-subject main teacher next to part main teachers, or a part the subject does not have, is refused.
        $this->assignTeacher($line, $helper, 1, 'f-whole')->assertSessionHasErrors(['teacher' => 'teachers.curriculum_part_conflict']);
        $this->from('/curriculum/curricula')->withHeader('X-Idempotency-Key', 'cst-assign-f-part')
            ->post('/curriculum/curriculum-subjects/'.$line.'/teachers', ['teacher_id' => $helper, 'teacher_role' => 2, 'subject_type' => 1])
            ->assertSessionHasErrors(['teacher' => 'teachers.teaching_part_invalid']);
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

    private function assignTeacher(int $line, int $teacher, int $role, string $key, ?int $section = null): \Illuminate\Testing\TestResponse
    {
        return $this->from('/curriculum/curricula')->withHeader('X-Idempotency-Key', 'cst-assign-'.$key)
            ->post('/curriculum/curriculum-subjects/'.$line.'/teachers', ['teacher_id' => $teacher, 'teacher_role' => $role, 'section_id' => $section]);
    }

    /** A class-level curriculum (no section — each teacher names his own). @param  array<string, mixed>  $ctx */
    private function createClassCurriculum(array $ctx, string $key): int
    {
        $this->from('/curriculum/curricula')->withHeader('X-Idempotency-Key', $key)
            ->post('/curriculum/curricula', $this->curriculumPayload($ctx, 'A', ['section_id' => null]))
            ->assertSessionHas('success', 'flash.curriculum.created');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school']]);

        return (int) DB::table('curriculum.curricula')->where('school_id', $ctx['school'])->whereNull('section_id')->value('id');
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
            'planned_subject_count' => 1,
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
