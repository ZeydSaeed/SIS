<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;

/**
 * The timetable engine end to end on PostgreSQL: settings → activities from the curriculum → groups →
 * availability → rules → generation run (queue, sync in tests) → apply → lock → version → workflow approval →
 * publish → effective timetable → restore; plus the gates (advisor, stale run, what-if, authorization, RLS).
 */
final class TimetableEnginePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    private int $key = 0;

    #[Test]
    public function data_to_published_timetable_through_generation_and_workflow_approval(): void
    {
        $ctx = $this->seedSchool('TE-A');
        $y = $ctx['year'];

        // Settings: Sunday–Thursday, one-week cycle, teacher ≤ 5 a day.
        $this->engine('/timetable/settings', ['academic_year_id' => $y, 'working_days' => [1, 2, 3, 4, 5], 'cycle_weeks' => 1,
            'max_teacher_per_day' => 5, 'max_subject_per_day' => 2, 'double_changeover_minutes' => 10])
            ->assertSessionHas('success', 'flash.timetable.engine.saved');

        // Activities from the curriculum: 2 sections × (theory 3 singles + practical 2 as a double).
        $this->engine('/timetable/activities/sync', ['academic_year_id' => $y])->assertSessionHasNoErrors();
        $activities = DB::table(SchemaHelper::qualified('timetable', 'activities'))->where('school_id', $ctx['school'])->orderBy('id')->get(['id', 'subject_id', 'block_length', 'weekly_count']);
        $this->assertCount(4, $activities);
        $this->assertSame([2], $activities->where('subject_id', $ctx['practical'])->pluck('block_length')->map(fn ($b): int => (int) $b)->unique()->values()->all());

        // Section A splits into 2 groups (its 4 students dealt 2 + 2).
        $this->engine('/timetable/divisions', ['academic_year_id' => $y, 'section_id' => $ctx['sectionA'], 'name' => 'العملي', 'group_count' => 2])
            ->assertSessionHasNoErrors();
        $groups = DB::table(SchemaHelper::qualified('timetable', 'division_groups'))->where('school_id', $ctx['school'])->orderBy('id')->pluck('student_count')->map(fn ($c): int => (int) $c)->all();
        $this->assertSame([2, 2], $groups);

        // The theory teacher is off on Sunday; a rule keeps the last lesson free (LOW).
        $this->engine('/timetable/availability', ['academic_year_id' => $y, 'target_type' => 'teacher', 'target_id' => $ctx['theoryTeacher'], 'kind' => 1,
            'slots' => array_map(fn (int $p): array => ['day' => 1, 'period_id' => $p], $ctx['periods'])])->assertSessionHasNoErrors();
        $this->engine('/timetable/rules', ['academic_year_id' => $y, 'rule_type' => 'forbidden_slots', 'priority' => 5, 'params' => ['lessons' => [4]], 'reason' => 'آخر حصة حرة'])
            ->assertSessionHas('success', 'flash.timetable.engine.ruleSaved');
        $this->engine('/timetable/rules', ['academic_year_id' => $y, 'rule_type' => 'teacher_max_per_day', 'priority' => 3, 'scope' => ['section_id' => $ctx['sectionA']], 'params' => ['max' => 4]])
            ->assertSessionHasErrors(['engine' => 'timetable.rule_scope_not_allowed']);

        // The page carries the engine (settings, activities, groups, availability, rules, catalogue).
        $this->get('/timetable?academic_year_id='.$y)->assertInertia(fn ($page) => $page
            ->where('engine.settings.max_teacher_per_day', 5)
            ->has('engine.activities', 4)
            ->has('engine.groups', 2)
            ->has('engine.rules', 1)
            ->where('advice.verdict', 'ready')
            ->where('authorization.can_generate', true)
            ->etc());

        // Generate (balanced): queued → executed (sync queue) → succeeded, nothing on the grid yet.
        $this->engine('/timetable/runs', ['academic_year_id' => $y, 'mode' => 2, 'time_budget' => 5, 'objectives' => ['minimize_teacher_gaps']])
            ->assertSessionHas('success', 'flash.timetable.engine.runQueued');
        $run = DB::table(SchemaHelper::qualified('timetable', 'generation_runs'))->where('school_id', $ctx['school'])->first();
        $this->assertSame(3, (int) $run->status, (string) $run->error);
        $this->assertSame(0, (int) $run->unplaced);
        $this->assertSame(0, (int) $run->hard_violations);
        $this->assertNotNull($run->input_snapshot);
        $this->assertSame([], $this->activeIds($ctx['school']));

        // Review (partial reload) then apply.
        $this->get('/timetable?academic_year_id='.$y.'&run='.$run->id)->assertInertia(fn ($page) => $page
            ->missing('runDetail')
            ->reloadOnly('runDetail', fn ($reload) => $reload
                ->where('runDetail.status', 3)
                ->where('runDetail.result.unplaced', [])
                ->etc()));
        $this->engine('/timetable/runs/'.$run->id.'/apply', [])->assertSessionHas('success', 'flash.timetable.engine.runApplied');
        $rows = DB::table(SchemaHelper::qualified('timetable', 'schedules'))->where('school_id', $ctx['school'])->whereNull('cancelled_at')->get();
        $this->assertCount(10, $rows, '2 sections × 5 lessons');
        $this->assertSame(0, $rows->where('teacher_id', $ctx['theoryTeacher'])->where('day_of_week', 1)->count(), 'theory teacher off on Sunday');
        $this->assertSame(6, (int) DB::table(SchemaHelper::qualified('timetable', 'generation_runs'))->where('id', $run->id)->value('status'));

        // Lock a lesson: it can no longer be moved by hand.
        $locked = (int) $rows->first()->id;
        $this->engine('/timetable/schedules/lock', ['academic_year_id' => $y, 'schedule_ids' => [$locked], 'lock' => true])->assertSessionHasNoErrors();
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'move-locked')
            ->post('/timetable/schedules/'.$locked.'/shift', ['direction' => 1])
            ->assertSessionHas('error', 'timetable.schedule_locked');

        // Version → submit (workflow request, default one-step flow) → approve as the approver → publish.
        $this->engine('/timetable/versions', ['academic_year_id' => $y, 'name' => 'الفصل الأول'])->assertSessionHas('success', 'flash.timetable.engine.versionCreated');
        $version = DB::table(SchemaHelper::qualified('timetable', 'versions'))->where('school_id', $ctx['school'])->first();
        $this->assertSame(10, (int) $version->entries_count);
        $this->engine('/timetable/versions/'.$version->id.'/submit', [])->assertSessionHas('success', 'flash.timetable.engine.versionSubmitted');
        $request = DB::table(SchemaHelper::qualified('workflow', 'approval_requests'))->where('entity_type', 'timetable_version')->where('entity_id', $version->id)->first();
        $this->assertNotNull($request);
        $this->assertSame(1, (int) $request->status);
        $this->engine('/timetable/versions/'.$version->id.'/publish', ['effective_from' => '2026-10-11'])
            ->assertSessionHasErrors(['engine' => 'timetable.version_not_approved']);

        $this->engine('/timetable/versions/'.$version->id.'/decide', ['decision' => 'approve'])->assertForbidden();
        $approver = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantTimetableApprover($approver, $ctx['school']);
        Sanctum::actingAs($approver);
        $this->engine('/timetable/versions/'.$version->id.'/decide', ['decision' => 'approve'])->assertSessionHas('success', 'flash.timetable.engine.versionDecided');
        $this->assertSame(3, (int) DB::table(SchemaHelper::qualified('timetable', 'versions'))->where('id', $version->id)->value('status'));
        $this->assertSame(2, (int) DB::table(SchemaHelper::qualified('workflow', 'approval_requests'))->where('id', $request->id)->value('status'));

        Sanctum::actingAs($ctx['user']);
        $this->engine('/timetable/versions/'.$version->id.'/publish', ['effective_from' => '2026-10-11'])->assertSessionHas('success', 'flash.timetable.engine.versionPublished');
        $this->assertSame(5, (int) DB::table(SchemaHelper::qualified('timetable', 'versions'))->where('id', $version->id)->value('status'));
        $this->expectsImmutableEntries((int) $version->id);

        // The effective timetable of Monday 2026-10-12 (day 2) comes from the published version.
        $monday = $this->getJson('/api/v1/timetable/effective?academic_year_id='.$y.'&date=2026-10-12&section_id='.$ctx['sectionB'])->assertOk();
        $this->assertSame((int) $version->id, $monday->json('meta.version_id'));
        $this->assertSame(2, $monday->json('meta.day_of_week'));
        foreach ($monday->json('data') as $lesson) {
            $this->assertSame(2, $lesson['day_of_week']);
        }

        // A student's week: section lessons + only their group's.
        $student = $this->getJson('/api/v1/timetable/students/'.$ctx['studentA'].'?academic_year_id='.$y)->assertOk();
        $this->assertSame($ctx['sectionA'], $student->json('meta.section_id'));
        $this->assertCount(5, $student->json('data'));

        // Rollback: the working grid back to the version after a manual change.
        $this->engine('/timetable/schedules/lock', ['academic_year_id' => $y, 'schedule_ids' => [$locked], 'lock' => false])->assertSessionHasNoErrors();
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'cancel-one')->post('/timetable/schedules/'.$locked.'/cancel')->assertSessionHasNoErrors();
        $this->assertCount(9, $this->activeIds($ctx['school']));
        $this->engine('/timetable/versions/'.$version->id.'/restore', [])->assertSessionHas('success', 'flash.timetable.engine.versionRestored');
        $this->assertCount(10, $this->activeIds($ctx['school']));

        // CSV export.
        $csv = $this->get('/timetable/export?academic_year_id='.$y.'&by=teacher')->assertOk();
        $this->assertStringContainsString('المعلم', $csv->streamedContent());
    }

    #[Test]
    public function generation_is_blocked_by_the_advisor_and_runs_never_overlap_or_apply_stale(): void
    {
        $ctx = $this->seedSchool('TE-B', lessons: 1);
        $y = $ctx['year'];

        // One lesson a day: the practical double has no slot → BLOCKED with the reasons.
        $this->engine('/timetable/runs', ['academic_year_id' => $y, 'mode' => 2])
            ->assertSessionHasErrors(['engine' => 'timetable.generation_blocked'])
            ->assertSessionHas('engineBlockers');
        $this->assertSame(0, DB::table(SchemaHelper::qualified('timetable', 'generation_runs'))->where('school_id', $ctx['school'])->count());

        // A second lesson period → ready. A what-if run cannot be applied.
        $this->addPeriod($ctx['school'], 2, '08:45', '09:30');
        $this->engine('/timetable/runs', ['academic_year_id' => $y, 'mode' => 2, 'time_budget' => 3,
            'what_if' => [['type' => 'teacher_absent', 'id' => $ctx['theoryTeacher'], 'days' => [2]]]])->assertSessionHasNoErrors();
        $whatIf = DB::table(SchemaHelper::qualified('timetable', 'generation_runs'))->where('school_id', $ctx['school'])->orderByDesc('id')->first();
        $this->assertTrue((bool) $whatIf->is_what_if);
        $this->engine('/timetable/runs/'.$whatIf->id.'/apply', [])->assertSessionHasErrors(['engine' => 'timetable.generation_what_if']);
        $this->engine('/timetable/runs/'.$whatIf->id.'/discard', [])->assertSessionHasNoErrors();

        // A run whose grid changed since it ran is stale.
        $this->engine('/timetable/runs', ['academic_year_id' => $y, 'mode' => 2, 'time_budget' => 3])->assertSessionHasNoErrors();
        $run = DB::table(SchemaHelper::qualified('timetable', 'generation_runs'))->where('school_id', $ctx['school'])->orderByDesc('id')->first();
        $this->assertSame(3, (int) $run->status, (string) $run->error);
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'manual-lesson')
            ->post('/timetable/schedules', ['academic_year_id' => $y, 'section_id' => $ctx['sectionA'], 'subject_id' => $ctx['theory'], 'teacher_id' => $ctx['theoryTeacher'], 'day_of_week' => 5, 'period_id' => $ctx['periods'][0]])
            ->assertSessionHasNoErrors();
        $this->engine('/timetable/runs/'.$run->id.'/apply', [])->assertSessionHasErrors(['engine' => 'timetable.generation_stale']);

        // Only one active run per school-year (the database refuses a second queued run).
        DB::table(SchemaHelper::qualified('timetable', 'generation_runs'))->insert(['school_id' => $ctx['school'], 'academic_year_id' => $y, 'mode' => 2, 'status' => 1, 'solver' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $this->engine('/timetable/runs', ['academic_year_id' => $y, 'mode' => 2])->assertSessionHasErrors(['engine' => 'timetable.generation_running']);
    }

    #[Test]
    public function engine_writes_need_their_permission_and_are_idempotent(): void
    {
        $ctx = $this->seedSchool('TE-C');

        // Same idempotency key twice → one version.
        $this->engine('/timetable/versions', ['academic_year_id' => $ctx['year'], 'name' => 'نسخة'], 'same-key')->assertSessionHasErrors(['engine' => 'timetable.version_empty']);
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'add-1')
            ->post('/timetable/schedules', ['academic_year_id' => $ctx['year'], 'section_id' => $ctx['sectionA'], 'subject_id' => $ctx['theory'], 'teacher_id' => $ctx['theoryTeacher'], 'day_of_week' => 2, 'period_id' => $ctx['periods'][0]])
            ->assertSessionHasNoErrors();
        $this->engine('/timetable/versions', ['academic_year_id' => $ctx['year'], 'name' => 'نسخة'], 'v-key')->assertSessionHasNoErrors();
        $this->engine('/timetable/versions', ['academic_year_id' => $ctx['year'], 'name' => 'نسخة'], 'v-key')->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table(SchemaHelper::qualified('timetable', 'versions'))->where('school_id', $ctx['school'])->count());

        // Without the permission: forbidden, whatever the payload.
        $this->actingAsAuthenticatedWithoutPermissions();
        $this->withHeader('X-School-Id', (string) $ctx['school']);
        $this->engine('/timetable/runs', ['academic_year_id' => $ctx['year'], 'mode' => 2])->assertForbidden();
        $this->engine('/timetable/rules', ['academic_year_id' => $ctx['year'], 'rule_type' => 'section_no_gaps', 'priority' => 3])->assertForbidden();
        $this->engine('/timetable/settings', ['academic_year_id' => $ctx['year']])->assertForbidden();
    }

    #[Test]
    public function engine_tables_are_isolated_per_school_and_never_hard_deleted(): void
    {
        $a = $this->seedSchool('TE-D');
        $this->engine('/timetable/rules', ['academic_year_id' => $a['year'], 'rule_type' => 'section_no_gaps', 'priority' => 3])->assertSessionHasNoErrors();
        $other = $this->createSchool('SCH-TE-D2', 'Other');

        DB::beginTransaction();
        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $other]);
        $this->assertSame(0, DB::table(SchemaHelper::qualified('timetable', 'constraint_rules'))->count());
        $this->assertSame(0, DB::table(SchemaHelper::qualified('timetable', 'activities'))->count());
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $a['school']]);
        $this->assertSame(1, DB::table(SchemaHelper::qualified('timetable', 'constraint_rules'))->count());
        PostgreSqlRlsActor::reset();
        DB::rollBack();

        $this->expectException(QueryException::class);
        DB::table(SchemaHelper::qualified('timetable', 'constraint_rules'))->where('school_id', $a['school'])->delete();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function engine(string $url, array $data, ?string $key = null): TestResponse
    {
        return $this->from('/timetable')->withHeader('X-Idempotency-Key', $key ?? 'engine-'.(++$this->key))->post($url, $data);
    }

    private function expectsImmutableEntries(int $versionId): void
    {
        try {
            DB::transaction(fn () => DB::table(SchemaHelper::qualified('timetable', 'version_entries'))->where('version_id', $versionId)->update(['day_of_week' => 1]));
            $this->fail('version entries must be immutable');
        } catch (QueryException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }
    }

    /** @return list<int> */
    private function activeIds(int $schoolId): array
    {
        return DB::table(SchemaHelper::qualified('timetable', 'schedules'))->where('school_id', $schoolId)->whereNull('cancelled_at')
            ->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    private function addPeriod(int $schoolId, int $number, string $start, string $end): int
    {
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'period-'.$schoolId.'-'.$number)
            ->post('/timetable/periods', ['period_number' => $number, 'start_time' => $start, 'end_time' => $end, 'period_type' => 1])
            ->assertSessionHasNoErrors();

        return (int) DB::table(SchemaHelper::qualified('timetable', 'periods'))->where('school_id', $schoolId)->where('period_number', $number)->value('id');
    }

    /**
     * Vocational school: department curriculum with theory (3 a week) and practical (2 a week, a double), one class,
     * sections A (4 students) and B, a theory teacher and a practical teacher for the whole class, `lessons` periods.
     *
     * @return array<string, mixed>
     */
    private function seedSchool(string $tag, int $lessons = 4): array
    {
        $schoolId = $this->createSchool('SCH-'.$tag, 'School '.$tag);
        $yearId = $this->createAcademicYear('AY-'.$tag);
        $gradeId = $this->createGradeLevel('G-'.$tag);
        $branchId = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
            'school_id' => $schoolId, 'code' => 'BR-'.$tag, 'name' => 'الصناعي', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $departmentId = $this->createDepartmentForSchool($schoolId, 'الكهرباء', $branchId);
        $subject = static fn (string $code, string $name, int $type): int => (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => $code.'-'.$tag, 'name' => $name, 'name_en' => $code.'-'.$tag, 'subject_type' => $type, 'credit_hours' => 3,
            'max_grade' => 100, 'pass_grade' => 50, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $theory = $subject('ELT', 'كهرباء نظري', 1);
        $practical = $subject('ELP', 'كهرباء عملي', 3);
        $curriculumId = $this->createCurriculumWithSubjects($schoolId, $yearId, $gradeId, [$theory, $practical], null, $departmentId);
        DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))->where('curriculum_id', $curriculumId)->where('subject_id', $theory)->update(['weekly_hours' => 3]);
        DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))->where('curriculum_id', $curriculumId)->where('subject_id', $practical)->update(['weekly_hours' => 2]);
        $class = $this->createClassForSchool($schoolId, $yearId, $gradeId);
        $sectionA = (int) $this->createSectionForClass((int) $class->id)->id;
        $sectionB = (int) $this->createSectionForClass((int) $class->id)->id;

        $students = [];
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        for ($i = 0; $i < 4; $i++) {
            $student = $this->createStudentForSchool($schoolId, ['full_name' => 'طالب '.$i]);
            $students[] = (int) $student->id;
            DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->insert([
                'student_id' => $student->id, 'academic_year_id' => $yearId, 'school_id' => $schoolId, 'branch_id' => $branchId,
                'department_id' => $departmentId, 'class_id' => $class->id, 'section_id' => $sectionA, 'status' => 1,
                'effective_from' => '2026-09-01', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $user = $this->actingAsTeachersManagerForSchool($schoolId);
        $this->actingAsTimetableManagerForSchool($schoolId, $user);
        $teacher = function (string $code, string $name, int $subjectId) use ($yearId, $branchId, $departmentId, $class): int {
            $this->from('/teachers')->withHeader('X-Idempotency-Key', 'register-'.$code)
                ->post('/teachers', ['academic_year_id' => $yearId, 'employee_code' => $code, 'first_name' => $name, 'last_name' => 'حسن'])
                ->assertSessionHas('success', 'flash.teachers.registered');
            $id = (int) DB::table('teachers.teachers')->where('employee_code', $code)->value('id');
            $this->from('/teachers')->withHeader('X-Idempotency-Key', 'assign-'.$code)
                ->post('/teachers/'.$id.'/assignments', ['academic_year_id' => $yearId, 'subject_id' => $subjectId, 'branch_id' => $branchId,
                    'department_id' => $departmentId, 'class_id' => (int) $class->id, 'section_id' => null])
                ->assertSessionHas('success', 'flash.teachers.assignmentAdded');

            return $id;
        };
        $theoryTeacher = $teacher('EMP-T-'.$tag, 'علي', $theory);
        $practicalTeacher = $teacher('EMP-P-'.$tag, 'سعيد', $practical);

        $times = [['08:00', '08:45'], ['08:45', '09:30'], ['09:30', '10:15'], ['10:15', '11:00']];
        $periods = [];
        for ($n = 1; $n <= $lessons; $n++) {
            $periods[] = $this->addPeriod($schoolId, $n, $times[$n - 1][0], $times[$n - 1][1]);
        }

        return [
            'school' => $schoolId, 'year' => $yearId, 'class' => (int) $class->id, 'sectionA' => $sectionA, 'sectionB' => $sectionB,
            'theory' => $theory, 'practical' => $practical, 'theoryTeacher' => $theoryTeacher, 'practicalTeacher' => $practicalTeacher,
            'periods' => $periods, 'user' => $user, 'studentA' => $students[0],
        ];
    }
}
