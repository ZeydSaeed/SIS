<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Infrastructure\Persistence\Eloquent\SecurityAuditLogRecord;
use App\Models\User;
use App\Security\Audit\SecurityEventType;
use App\Security\Authorization\Permission;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

/**
 * EXAMIS U2 — grade entry is limited to seats the teacher's own teaching assignment covers;
 * grades.enter.any (grades_manager) enters school-wide and grants nothing beyond entry.
 */
final class GradeEntryTeacherScopePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    /**
     * Seat: school A, year Y, subject S, branch B, department D, class C, section X (+ siblings for negatives).
     *
     * @return array<string, int>
     */
    private function seedScene(): array
    {
        $school = $this->createSchool('SCH-GTS', 'Grade Teacher Scope');
        $graph = $this->seedExamGradeGraph($school, suffix: 'GTS');
        $this->markSessionInProgressForGradeEntry($graph['session_id']);

        $enrollment = DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->where('id', $graph['enrollment_id'])->first();
        $branch = $this->branch($school, 'فرع الحاسوب');
        $department = $this->createDepartmentForSchool($school, 'شبكات', $branch);
        DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('id', $graph['enrollment_id'])
            ->update(['branch_id' => $branch, 'department_id' => $department]);

        $otherSection = (int) $this->createSectionForClass((int) $enrollment->class_id)->id;
        $otherClass = (int) $this->createClassForSchool($school, $graph['year_id'])->id;

        return [
            'school' => $school,
            'year' => $graph['year_id'],
            'subject' => $graph['subject_id'],
            'seat' => $graph['exam_enrollment_id'],
            'branch' => $branch,
            'department' => $department,
            'class' => (int) $enrollment->class_id,
            'section' => (int) $enrollment->section_id,
            'other_section' => $otherSection,
            'other_class' => $otherClass,
            'other_branch' => $this->branch($school, 'فرع المحاسبة'),
            'other_department' => $this->createDepartmentForSchool($school, 'برمجيات', $branch),
            'other_subject' => (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
                'code' => 'OTH'.substr(uniqid(), -4), 'name' => 'Other subject', 'subject_type' => 1, 'status' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]),
            'other_year' => $this->createAcademicYear('AY-GTS-OTHER'),
        ];
    }

    private function branch(int $schoolId, string $name): int
    {
        return (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
            'school_id' => $schoolId,
            'code' => 'BR-'.substr(md5($name.uniqid()), 0, 8),
            'name' => $name,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** A grades_teacher user linked to an active teacher who is a current member of the school-year. */
    private function teacherUser(array $s, bool $linked = true, bool $leftSchool = false): array
    {
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantGradesTeacher($user, $s['school']);
        $teacherId = (int) DB::table(SchemaHelper::qualified('teachers', 'teachers'))->insertGetId([
            'user_id' => $linked ? $user->id : null,
            'employee_code' => 'EMP-'.uniqid(),
            'first_name' => 'معلم',
            'last_name' => 'اختبار',
            'full_name' => 'معلم اختبار',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))->insert([
            'teacher_id' => $teacherId,
            'school_id' => $s['school'],
            'academic_year_id' => $s['year'],
            'is_primary' => true,
            'left_at' => $leftSchool ? now() : null,
        ]);

        return [$user, $teacherId];
    }

    /** @param  array<string, mixed>  $overrides  column => value | scene key (string) */
    private function assign(int $teacherId, array $s, array $overrides = []): void
    {
        $row = array_merge([
            'subject_id' => 'subject', 'branch_id' => 'branch', 'department_id' => 'department',
            'class_id' => 'class', 'section_id' => 'section', 'academic_year_id' => 'year',
        ], $overrides);
        foreach ($row as $column => $value) {
            $row[$column] = is_string($value) && array_key_exists($value, $s) ? $s[$value] : $value;
        }

        DB::table(SchemaHelper::qualified('teachers', 'teaching_assignments'))->insert($row + [
            'teacher_id' => $teacherId,
            'school_id' => $s['school'],
            'status' => 1,
            'effective_from' => now()->subMonth()->toDateString(),
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function enter(int $seat, string $key): TestResponse
    {
        return $this->postJson('/api/v1/grades', [
            'exam_enrollment_id' => $seat,
            'score' => 77,
            'is_absent' => false,
        ], ['X-Idempotency-Key' => $key]);
    }

    private function actAs(User $user, int $schoolId): void
    {
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);
    }

    private function gradeCount(int $seat): int
    {
        return DB::table(SchemaHelper::qualified('exams', 'student_grades'))->where('exam_enrollment_id', $seat)->count();
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function coveringAssignments(): iterable
    {
        yield 'exact section' => [[]];
        yield 'whole class' => [['section_id' => null]];
        yield 'whole department' => [['class_id' => null, 'section_id' => null]];
        yield 'whole branch' => [['department_id' => null, 'class_id' => null, 'section_id' => null]];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[Test]
    #[DataProvider('coveringAssignments')]
    public function teacher_enters_grade_for_a_covered_seat(array $overrides): void
    {
        $s = $this->seedScene();
        [$user, $teacherId] = $this->teacherUser($s);
        $this->assign($teacherId, $s, $overrides);
        $this->actAs($user, $s['school']);

        $this->enter($s['seat'], 'gts-ok-'.md5(serialize($overrides)))->assertCreated();
        $this->assertSame(1, $this->gradeCount($s['seat']));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function nonCoveringAssignments(): iterable
    {
        yield 'other subject' => [['subject_id' => 'other_subject']];
        yield 'other section of the same class' => [['section_id' => 'other_section']];
        yield 'other class' => [['class_id' => 'other_class', 'section_id' => null]];
        yield 'other department' => [['department_id' => 'other_department', 'class_id' => null, 'section_id' => null]];
        yield 'other branch' => [['branch_id' => 'other_branch', 'department_id' => null, 'class_id' => null, 'section_id' => null]];
        yield 'other academic year' => [['academic_year_id' => 'other_year']];
        yield 'ended assignment' => [['status' => 2, 'effective_to' => '2026-01-01', 'effective_from' => '2025-09-01']];
        yield 'assignment not started yet' => [['effective_from' => '2999-01-01']];
        yield 'assignment ended before today' => [['effective_from' => '2025-09-01', 'effective_to' => '2025-12-31']];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[Test]
    #[DataProvider('nonCoveringAssignments')]
    public function teacher_is_denied_for_a_seat_outside_the_assignment(array $overrides): void
    {
        $s = $this->seedScene();
        [$user, $teacherId] = $this->teacherUser($s);
        $this->assign($teacherId, $s, $overrides);
        $this->actAs($user, $s['school']);

        $this->enter($s['seat'], 'gts-deny-'.md5(serialize($overrides)))->assertForbidden();
        $this->assertSame(0, $this->gradeCount($s['seat']));
    }

    #[Test]
    public function teacher_without_any_assignment_is_denied_and_the_denial_is_audited(): void
    {
        $s = $this->seedScene();
        [$user] = $this->teacherUser($s);
        $this->actAs($user, $s['school']);

        $this->enter($s['seat'], 'gts-none')->assertForbidden();

        $this->assertSame(0, $this->gradeCount($s['seat']));
        $this->assertTrue(SecurityAuditLogRecord::query()
            ->where('event_id', SecurityEventType::IdorBlocked->value)
            ->where('actor_id', $user->id)
            ->where('action', 'grades.enter.scope')
            ->where('target_id', (string) $s['seat'])
            ->exists());
    }

    #[Test]
    public function grades_teacher_without_a_linked_teacher_record_is_denied(): void
    {
        $s = $this->seedScene();
        [$user, $teacherId] = $this->teacherUser($s, linked: false);
        $this->assign($teacherId, $s);
        $this->actAs($user, $s['school']);

        $this->enter($s['seat'], 'gts-unlinked')->assertForbidden();
        $this->assertSame(0, $this->gradeCount($s['seat']));
    }

    #[Test]
    public function teacher_who_left_the_school_is_denied(): void
    {
        $s = $this->seedScene();
        [$user, $teacherId] = $this->teacherUser($s, leftSchool: true);
        $this->assign($teacherId, $s);
        $this->actAs($user, $s['school']);

        $this->enter($s['seat'], 'gts-left')->assertForbidden();
    }

    #[Test]
    public function seat_without_branch_placement_is_denied_to_teachers(): void
    {
        $s = $this->seedScene();
        DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->whereIn('id', DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))->where('id', $s['seat'])->select('enrollment_id'))
            ->update(['branch_id' => null, 'department_id' => null]);
        [$user, $teacherId] = $this->teacherUser($s);
        $this->assign($teacherId, $s, ['department_id' => null, 'class_id' => null, 'section_id' => null]);
        $this->actAs($user, $s['school']);

        $this->enter($s['seat'], 'gts-nobranch')->assertForbidden();
    }

    #[Test]
    public function teacher_cannot_reach_another_schools_seat(): void
    {
        $s = $this->seedScene();
        [$user, $teacherId] = $this->teacherUser($s);
        $this->assign($teacherId, $s, ['department_id' => null, 'class_id' => null, 'section_id' => null]);

        $otherSchool = $this->createSchool('SCH-GTS-B', 'Other school');
        $foreign = $this->seedExamGradeGraph($otherSchool, $s['year'], 'GTSB');
        $this->markSessionInProgressForGradeEntry($foreign['session_id']);
        $this->actAs($user, $s['school']);

        $this->enter($foreign['exam_enrollment_id'], 'gts-cross')->assertForbidden();
        $this->assertSame(0, $this->gradeCount($foreign['exam_enrollment_id']));
    }

    #[Test]
    public function invalid_payload_is_still_a_validation_error_for_teachers(): void
    {
        $s = $this->seedScene();
        [$user] = $this->teacherUser($s);
        $this->actAs($user, $s['school']);

        $this->postJson('/api/v1/grades', ['score' => 50, 'is_absent' => false], ['X-Idempotency-Key' => 'gts-422'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('exam_enrollment_id');
    }

    #[Test]
    public function grades_manager_enters_school_wide_without_a_teacher_record(): void
    {
        $s = $this->seedScene();
        $this->actingAsGradesManagerForSchool($s['school']);

        $this->enter($s['seat'], 'gts-manager')->assertCreated();
        $this->assertSame(1, $this->gradeCount($s['seat']));
    }

    #[Test]
    public function enter_any_is_registered_on_grades_manager_only(): void
    {
        $this->assertArrayHasKey(Permission::GRADES_ENTER_ANY, config('security.permissions'));
        $this->assertContains(Permission::GRADES_ENTER_ANY, Permission::all());

        $holders = array_keys(array_filter(
            config('security.roles'),
            static fn (array $permissions): bool => in_array(Permission::GRADES_ENTER_ANY, $permissions, true),
        ));
        $this->assertSame(['grades_manager'], $holders);
    }

    #[Test]
    public function enter_any_grants_no_correct_void_or_finalize(): void
    {
        $s = $this->seedScene();
        $user = User::factory()->create();
        $this->grantAdHocRole($user, $s['school'], 'grades_enter_any_only', [
            Permission::GRADES_VIEW, Permission::GRADES_CREATE, Permission::GRADES_ENTER_ANY,
        ]);
        $this->actAs($user, $s['school']);

        $gradeId = (int) $this->enter($s['seat'], 'gts-any-only')->assertCreated()->json('data.id');
        $year = ['academic_year_id' => $s['year']];

        $this->postJson("/api/v1/grades/{$gradeId}/correct", $year + ['score' => 80, 'is_absent' => false, 'reason' => 'typo'], ['X-Idempotency-Key' => 'gts-c'])
            ->assertForbidden();
        $this->postJson("/api/v1/grades/{$gradeId}/void", $year + ['reason' => 'x'], ['X-Idempotency-Key' => 'gts-v'])
            ->assertForbidden();
        $this->postJson("/api/v1/grades/{$gradeId}/finalize", $year, ['X-Idempotency-Key' => 'gts-f'])
            ->assertForbidden();
    }

    #[Test]
    public function web_grade_form_applies_the_same_scope(): void
    {
        $s = $this->seedScene();
        [$user] = $this->teacherUser($s);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $s['school']]);

        $this->post('/grades', ['exam_enrollment_id' => $s['seat'], 'score' => 70, 'is_absent' => 0], ['X-Idempotency-Key' => 'gts-web'])
            ->assertForbidden();
        $this->assertSame(0, $this->gradeCount($s['seat']));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function grantAdHocRole(User $user, int $schoolId, string $code, array $permissions): void
    {
        $seeder = app(SecurityPermissionSeeder::class);
        $seeder->run();
        $roleId = (int) DB::table(SchemaHelper::qualified('security', 'roles'))->insertGetId([
            'code' => $code, 'name' => $code, 'description' => null, 'is_system' => false,
        ]);
        foreach ($permissions as $permission) {
            DB::table(SchemaHelper::qualified('security', 'role_permissions'))->insert([
                'role_id' => $roleId,
                'permission_id' => (int) DB::table(SchemaHelper::qualified('security', 'permissions'))->where('code', $permission)->value('id'),
            ]);
        }
        $seeder->assignRole($user, $code, $schoolId);
    }
}
