<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/** «الجدول الدراسي» builder: توقيت الحصص, lessons to place (with weekly hours), place / move / take off, conflicts. */
final class TimetableBuilderPagePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function the_school_day_is_defined_and_lessons_are_placed_moved_and_taken_off(): void
    {
        $ctx = $this->seedSchool('TB-A');

        // School day: overlapping times are rejected; a break holds no lessons.
        $p1 = $this->addPeriod(1, '08:00', '08:45', 1);
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'tb-a-overlap')
            ->post('/timetable/periods', ['period_number' => 2, 'start_time' => '08:30', 'end_time' => '09:00', 'period_type' => 1])
            ->assertSessionHasErrors(['period' => 'timetable.period_overlap']);
        $break = $this->addPeriod(2, '08:45', '09:00', 2);
        $p3 = $this->addPeriod(3, '09:00', '09:45', 1);

        $this->get('/timetable?academic_year_id='.$ctx['year'])
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('timetable/index')
                ->has('periods', 3)
                ->where('periods.1.period_type', 2)
                ->where('periods.2.start_time', '09:00')
                ->where('sections.0.id', $ctx['section'])
                ->where('lessons.0.teacher_id', $ctx['teacher'])
                ->where('lessons.0.subject_id', $ctx['networks'])
                ->where('lessons.0.weekly_hours', 3)
                ->where('schedules', [])
                ->where('authorization.can_manage_periods', true)
                ->etc());

        $lesson = ['academic_year_id' => $ctx['year'], 'section_id' => $ctx['section'], 'subject_id' => $ctx['networks'], 'teacher_id' => $ctx['teacher']];

        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'tb-a-break')
            ->post('/timetable/schedules', $lesson + ['day_of_week' => 1, 'period_id' => $break])
            ->assertSessionHas('error', 'timetable.period_not_lesson');

        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'tb-a-place')
            ->post('/timetable/schedules', $lesson + ['day_of_week' => 1, 'period_id' => $p1])
            ->assertSessionMissing('error');
        $scheduleId = $this->activeScheduleIds($ctx['school'])[0];

        // Move to Monday · period 3.
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'tb-a-move')
            ->patch('/timetable/schedules/'.$scheduleId, $lesson + ['day_of_week' => 2, 'period_id' => $p3])
            ->assertSessionMissing('error');
        $this->get('/timetable?academic_year_id='.$ctx['year'])
            ->assertInertia(fn ($page) => $page
                ->where('schedules.0.id', $scheduleId)
                ->where('schedules.0.day_of_week', 2)
                ->where('schedules.0.period_id', $p3)
                ->etc());

        // A period holding a lesson cannot become a break.
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'tb-a-to-break')
            ->patch('/timetable/periods/'.$p3, ['period_number' => 3, 'start_time' => '09:00', 'end_time' => '09:45', 'period_type' => 2])
            ->assertSessionHasErrors(['period' => 'timetable.period_has_lessons']);

        // Take off the grid: soft cancel (history kept), the lesson returns to the tray.
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'tb-a-cancel')
            ->post('/timetable/schedules/'.$scheduleId.'/cancel')
            ->assertSessionMissing('error');
        $this->assertSame([], $this->activeScheduleIds($ctx['school']));
        $this->assertSame(2, (int) DB::table(SchemaHelper::qualified('timetable', 'schedules'))->where('id', $scheduleId)->value('lifecycle_status'));

        // Re-time a period.
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'tb-a-retime')
            ->patch('/timetable/periods/'.$p3, ['period_number' => 3, 'start_time' => '09:00', 'end_time' => '09:40', 'period_type' => 1])
            ->assertSessionHas('success', 'flash.timetable.periodUpdated');
    }

    #[Test]
    public function a_teacher_is_never_in_two_sections_at_once(): void
    {
        $ctx = $this->seedSchool('TB-B');
        $period = $this->addPeriod(1, '08:00', '08:45', 1);
        $otherSection = (int) $this->createSectionForClass($ctx['class'])->id;

        $lesson = ['academic_year_id' => $ctx['year'], 'subject_id' => $ctx['networks'], 'teacher_id' => $ctx['teacher'], 'day_of_week' => 3, 'period_id' => $period];
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'tb-b-first')
            ->post('/timetable/schedules', $lesson + ['section_id' => $ctx['section']])
            ->assertSessionMissing('error');
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'tb-b-second')
            ->post('/timetable/schedules', $lesson + ['section_id' => $otherSection])
            ->assertSessionHas('error', 'timetable.teacher_slot_conflict');

        $this->assertCount(1, $this->activeScheduleIds($ctx['school']));
    }

    #[Test]
    public function auto_place_then_swap_shift_and_audit(): void
    {
        $ctx = $this->seedSchool('TB-D');
        $p1 = $this->addPeriod(1, '08:00', '08:45', 1);
        $p2 = $this->addPeriod(2, '08:45', '09:30', 1);
        $this->addPeriod(3, '09:30', '10:15', 1);

        // «توزيع تلقائي»: the 3 weekly lessons, one a day, from the first period.
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'tb-d-auto')
            ->post('/timetable/schedules/auto-place', ['academic_year_id' => $ctx['year'], 'section_ids' => [$ctx['section']]])
            ->assertSessionHas('success', 'flash.timetable.autoPlaced');
        $ids = $this->activeScheduleIds($ctx['school']);
        $this->assertCount(3, $ids);
        $rows = DB::table(SchemaHelper::qualified('timetable', 'schedules'))->whereIn('id', $ids)->orderBy('id')->get(['id', 'day_of_week', 'period_id']);
        $this->assertSame([1, 2, 3], $rows->pluck('day_of_week')->map(fn ($d): int => (int) $d)->all());
        $this->assertSame([$p1, $p1, $p1], $rows->pluck('period_id')->map(fn ($p): int => (int) $p)->all());

        // «استبدال»: the Sunday and Monday lessons trade days.
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'tb-d-swap')
            ->post('/timetable/schedules/'.$ids[0].'/swap', ['with_schedule_id' => $ids[1]])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');
        $this->assertSame(2, (int) DB::table(SchemaHelper::qualified('timetable', 'schedules'))->where('id', $ids[0])->value('day_of_week'));
        $this->assertSame(1, (int) DB::table(SchemaHelper::qualified('timetable', 'schedules'))->where('id', $ids[1])->value('day_of_week'));

        // «زحف»: one period later, then no room earlier than period 1.
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'tb-d-shift')
            ->post('/timetable/schedules/'.$ids[2].'/shift', ['direction' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame($p2, (int) DB::table(SchemaHelper::qualified('timetable', 'schedules'))->where('id', $ids[2])->value('period_id'));
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'tb-d-shift-no-room')
            ->post('/timetable/schedules/'.$ids[0].'/shift', ['direction' => -1])
            ->assertSessionHasErrors(['schedule' => 'timetable.shift_no_room']);

        // A lesson of another section cannot be swapped in.
        $other = (int) $this->createSectionForClass($ctx['class'])->id;
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'tb-d-other')
            ->post('/timetable/schedules', ['academic_year_id' => $ctx['year'], 'section_id' => $other, 'subject_id' => $ctx['networks'], 'teacher_id' => $ctx['teacher'], 'day_of_week' => 4, 'period_id' => $p1])
            ->assertSessionMissing('error');
        $otherId = $this->activeScheduleIds($ctx['school'])[3];
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'tb-d-swap-other')
            ->post('/timetable/schedules/'.$ids[0].'/swap', ['with_schedule_id' => $otherId])
            ->assertSessionHasErrors(['schedule' => 'timetable.swap_other_section']);

        // The page carries the audit: the other section's lesson is outside its plan → nothing over-placed for TB-D section;
        // the teacher may teach the subject, so no errors.
        $this->get('/timetable?academic_year_id='.$ctx['year'])
            ->assertInertia(fn ($page) => $page
                ->has('issues')
                ->where('teacherSubjects.0.teacher_id', $ctx['teacher'])
                ->where('practicalSubjectIds', [])
                ->etc());
    }

    #[Test]
    public function breaks_are_laid_out_automatically_and_lessons_stay_on_their_periods(): void
    {
        $ctx = $this->seedSchool('TB-E');
        $p1 = $this->addPeriod(1, '07:30', '08:10', 1);
        $p2 = $this->addPeriod(2, '08:10', '08:50', 1);
        $this->addPeriod(3, '08:50', '09:00', 2);
        $p4 = $this->addPeriod(4, '09:00', '09:40', 1);
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'tb-e-lesson')
            ->post('/timetable/schedules', ['academic_year_id' => $ctx['year'], 'section_id' => $ctx['section'], 'subject_id' => $ctx['networks'], 'teacher_id' => $ctx['teacher'], 'day_of_week' => 1, 'period_id' => $p4])
            ->assertSessionMissing('error');

        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'tb-e-arrange')
            ->post('/timetable/periods/arrange', ['start_time' => '08:00', 'lesson_minutes' => 45])
            ->assertSessionHas('success', 'flash.timetable.dayArranged');

        // 3 lessons: the main break (15) after lesson 1 (the middle), then 5 — the old break is re-timed, one is added.
        $day = DB::table(SchemaHelper::qualified('timetable', 'periods'))->where('school_id', $ctx['school'])->orderBy('period_number')
            ->get(['id', 'period_number', 'start_time', 'end_time', 'period_type']);
        $this->assertSame([1, 2, 3, 4, 5], $day->pluck('period_number')->map(fn ($n): int => (int) $n)->all());
        $this->assertSame([1, 2, 1, 2, 1], $day->pluck('period_type')->map(fn ($n): int => (int) $n)->all());
        $this->assertSame([$p1, $p2, $p4], $day->where('period_type', 1)->pluck('id')->map(fn ($n): int => (int) $n)->values()->all());
        $this->assertSame('08:00:00', (string) $day[0]->start_time);
        $this->assertSame('08:45:00', (string) $day[1]->start_time);
        $this->assertSame('09:00:00', (string) $day[1]->end_time);
        $this->assertSame('10:35:00', (string) $day[4]->end_time);

        // The lesson stayed on its period (now the third lesson).
        $this->assertSame($p4, (int) DB::table(SchemaHelper::qualified('timetable', 'schedules'))->where('school_id', $ctx['school'])->value('period_id'));

        // Section ↔ branch / department comes from the students' placement (none here).
        $this->get('/timetable?academic_year_id='.$ctx['year'])
            ->assertInertia(fn ($page) => $page->where('placements', [])->has('branches')->where('teachers.0.short_name', 'علي')->etc());
    }

    #[Test]
    public function a_viewer_cannot_change_the_school_day(): void
    {
        $schoolId = $this->createSchool('SCH-TB-C', 'School TB-C');
        $this->actingAsTeachersViewerForSchool($schoolId);

        $this->withHeader('X-Idempotency-Key', 'tb-c-period')
            ->post('/timetable/periods', ['period_number' => 1, 'start_time' => '08:00', 'end_time' => '08:45', 'period_type' => 1])
            ->assertForbidden();
    }

    private function addPeriod(int $number, string $start, string $end, int $type): int
    {
        $this->from('/timetable')
            ->withHeader('X-Idempotency-Key', 'period-'.$number.'-'.$start.'-'.uniqid())
            ->post('/timetable/periods', ['period_number' => $number, 'start_time' => $start, 'end_time' => $end, 'period_type' => $type])
            ->assertSessionHas('success', 'flash.timetable.periodAdded');

        return (int) DB::table(SchemaHelper::qualified('timetable', 'periods'))->where('period_number', $number)->orderByDesc('id')->value('id');
    }

    /** @return list<int> */
    private function activeScheduleIds(int $schoolId): array
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        return DB::table(SchemaHelper::qualified('timetable', 'schedules'))
            ->where('school_id', $schoolId)
            ->where('lifecycle_status', 1)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * A school whose teacher teaches «شبكات الحاسوب» (3 a week in the department curriculum) to one section.
     *
     * @return array{school:int, year:int, class:int, section:int, networks:int, teacher:int}
     */
    private function seedSchool(string $tag): array
    {
        $schoolId = $this->createSchool('SCH-'.$tag, 'School '.$tag);
        $yearId = $this->createAcademicYear('AY-'.$tag);
        $gradeId = $this->createGradeLevel('G-'.$tag);
        $branchId = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
            'school_id' => $schoolId, 'code' => 'BR-'.$tag, 'name' => 'الحاسوب وتقنية المعلومات', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $departmentId = $this->createDepartmentForSchool($schoolId, 'شبكات الحاسوب', $branchId);
        $networks = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'NET-'.$tag, 'name' => 'شبكات الحاسوب', 'name_en' => 'NET-'.$tag, 'subject_type' => 1, 'credit_hours' => 3,
            'max_grade' => 100, 'pass_grade' => 50, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $curriculumId = $this->createCurriculumWithSubjects($schoolId, $yearId, $gradeId, [$networks], null, $departmentId);
        DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))->where('curriculum_id', $curriculumId)->update(['weekly_hours' => 3]);
        $class = $this->createClassForSchool($schoolId, $yearId, $gradeId);
        $section = $this->createSectionForClass((int) $class->id);

        // Both roles before the first request (permissions are resolved once per user).
        $user = $this->actingAsTeachersManagerForSchool($schoolId);
        $this->actingAsTimetableManagerForSchool($schoolId, $user);
        $code = 'EMP-'.$tag;
        $this->from('/teachers')->withHeader('X-Idempotency-Key', 'register-'.$code)
            ->post('/teachers', ['academic_year_id' => $yearId, 'employee_code' => $code, 'first_name' => 'علي', 'last_name' => 'حسن'])
            ->assertSessionHas('success', 'flash.teachers.registered');
        $teacherId = (int) DB::table('teachers.teachers')->where('employee_code', $code)->value('id');
        $this->from('/teachers')->withHeader('X-Idempotency-Key', 'assign-'.$code)
            ->post('/teachers/'.$teacherId.'/assignments', [
                'academic_year_id' => $yearId, 'subject_id' => $networks, 'branch_id' => $branchId,
                'department_id' => $departmentId, 'class_id' => (int) $class->id, 'section_id' => null,
            ])
            ->assertSessionHas('success', 'flash.teachers.assignmentAdded');

        return ['school' => $schoolId, 'year' => $yearId, 'class' => (int) $class->id, 'section' => (int) $section->id, 'networks' => $networks, 'teacher' => $teacherId];
    }
}
