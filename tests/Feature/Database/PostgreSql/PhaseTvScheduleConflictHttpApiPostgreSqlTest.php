<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Timetable\Data\PersistScheduleData;
use App\Domain\Timetable\Data\PersistScheduleExceptionData;
use App\Domain\Timetable\Exceptions\ScheduleSlotConflictException;
use App\Domain\Timetable\Exceptions\ScheduleValidationException;
use App\Domain\Timetable\Repositories\ScheduleExceptionRepositoryInterface;
use App\Domain\Timetable\Repositories\ScheduleRepositoryInterface;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\TimetableTeachingFixture;

final class PhaseTvScheduleConflictHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function create_rejects_teacher_slot_conflict_with_409(): void
    {
        $ctx = $this->seedScheduleGraph('CF');
        $sectionB = $this->createSectionForClass(
            (int) DB::table(SchemaHelper::qualified('enrollment', 'sections'))
                ->where('id', $ctx['section_id'])
                ->value('class_id'),
        );

        $this->actingAsTimetableManagerForSchool($ctx['school_id']);

        $payload = [
            'academic_year_id' => $ctx['year_id'],
            'section_id' => $ctx['section_id'],
            'day_of_week' => 2,
            'period_id' => $ctx['period_id'],
            'subject_id' => $ctx['subject_id'],
            'teacher_id' => $ctx['teacher_id'],
            'room_id' => $ctx['room_id'],
        ];

        $this->postJson('/api/v1/timetable/schedules', $payload, [
            'X-Idempotency-Key' => 'tv-cf-create-1',
        ])->assertCreated();

        $this->postJson('/api/v1/timetable/schedules', [
            ...$payload,
            'section_id' => (int) $sectionB->id,
            'room_id' => null,
        ], [
            'X-Idempotency-Key' => 'tv-cf-create-2',
        ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'timetable.teacher_slot_conflict');
    }

    #[Test]
    public function a_workshop_and_its_room_hold_one_lesson_per_slot(): void
    {
        $ctx = $this->seedScheduleGraph('WS');
        $classId = (int) DB::table('enrollment.sections')->where('id', $ctx['section_id'])->value('class_id');
        $sectionB = (int) $this->createSectionForClass($classId)->id;
        $sectionC = (int) $this->createSectionForClass($classId)->id;
        $teacherB = $this->addTeacher($ctx, 'WS-B');
        $teacherC = $this->addTeacher($ctx, 'WS-C');
        $repo = app(ScheduleRepositoryInterface::class);

        // Workshop housed in the room; a lesson held there (through its activity, no room on the lesson).
        $housed = $this->addWorkshop($ctx, 'WS1', $ctx['room_id']);
        $repo->insertActive($this->lesson($ctx, $ctx['section_id'], $ctx['teacher_id'], null, $this->addActivity($ctx, $housed)));

        // The same room booked directly at that time clashes with the workshop lesson.
        $this->assertConflict('timetable.room_slot_conflict', fn () => $repo->insertActive($this->lesson($ctx, $sectionB, $teacherB, $ctx['room_id'])));

        // A workshop with no room: a second lesson in it at the same time clashes too.
        $bare = $this->addWorkshop($ctx, 'WS2', null);
        $repo->insertActive($this->lesson($ctx, $sectionB, $teacherB, null, $this->addActivity($ctx, $bare)));
        $this->assertConflict('timetable.workshop_slot_conflict', fn () => $repo->insertActive($this->lesson($ctx, $sectionC, $teacherC, null, $this->addActivity($ctx, $bare))));

        // Another day is free.
        $repo->insertActive($this->lesson($ctx, $sectionC, $teacherC, null, $this->addActivity($ctx, $bare), day: 3));
        $this->assertSame(3, DB::table('timetable.schedules')->where('school_id', $ctx['school_id'])->whereNull('cancelled_at')->count());
    }

    #[Test]
    public function a_teacher_busy_as_co_teacher_is_refused_on_reactivate_and_on_bulk_apply(): void
    {
        $ctx = $this->seedScheduleGraph('CO');
        $classId = (int) DB::table('enrollment.sections')->where('id', $ctx['section_id'])->value('class_id');
        $sectionB = (int) $this->createSectionForClass($classId)->id;
        $teacherB = $this->addTeacher($ctx, 'CO-B');
        $repo = app(ScheduleRepositoryInterface::class);

        // Lesson 1 (teacher A) is cancelled; lesson 2 (teacher B, co-teacher A) takes the slot.
        $first = $repo->insertActive($this->lesson($ctx, $ctx['section_id'], $ctx['teacher_id'], null));
        $repo->cancel($ctx['school_id'], $first, now()->toIso8601String());
        $repo->insertActive($this->lesson($ctx, $sectionB, $teacherB, null, coTeacher: $ctx['teacher_id']));

        // Reactivating lesson 1 would put teacher A in two lessons at once.
        $this->assertConflict('timetable.teacher_slot_conflict', fn () => $repo->reactivate($ctx['school_id'], $first, now()->toIso8601String()));

        // A bulk write (generation apply / version restore) with the same overlap is rolled back as well.
        $row = [
            'lead_key' => 'x', 'is_lead' => true, 'section_id' => $ctx['section_id'], 'group_id' => null, 'day_of_week' => 2,
            'period_id' => $ctx['period_id'], 'week_no' => null, 'subject_id' => $ctx['subject_id'], 'teacher_id' => $ctx['teacher_id'],
            'co_teacher_id' => null, 'room_id' => null, 'activity_id' => null,
        ];
        $this->assertConflict('timetable.teacher_slot_conflict', fn () => DB::transaction(fn () => $repo->replaceGrid($ctx['school_id'], $ctx['year_id'], [], [$row], now()->toIso8601String(), null, null)));
        $this->assertSame(1, DB::table('timetable.schedules')->where('school_id', $ctx['school_id'])->whereNull('cancelled_at')->count());
    }

    #[Test]
    public function a_teacher_teaching_in_another_school_at_that_time_is_refused(): void
    {
        $ctx = $this->seedScheduleGraph('XS');
        $repo = app(ScheduleRepositoryInterface::class);

        // The same teacher teaches in a second school, 08:30–09:10 on Monday (overlaps 08:00–08:45 here).
        $other = $this->createSchool('SCH-TV-XS2', 'TV XS2');
        $otherClass = $this->createClassForSchool($other, $ctx['year_id']);
        $otherSection = (int) $this->createSectionForClass((int) $otherClass->id)->id;
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $other]);
        $otherPeriod = (int) DB::table('timetable.periods')->insertGetId(['school_id' => $other, 'period_number' => 1, 'start_time' => '08:30:00', 'end_time' => '09:10:00', 'period_type' => 1]);
        DB::table('teachers.teacher_schools')->insert(['teacher_id' => $ctx['teacher_id'], 'school_id' => $other, 'academic_year_id' => $ctx['year_id'], 'is_primary' => false, 'created_at' => now()]);
        DB::table('timetable.schedules')->insert([
            'school_id' => $other, 'section_id' => $otherSection, 'academic_year_id' => $ctx['year_id'], 'day_of_week' => 2, 'period_id' => $otherPeriod,
            'subject_id' => $ctx['subject_id'], 'teacher_id' => $ctx['teacher_id'], 'lifecycle_status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertConflict('timetable.teacher_busy_other_school', fn () => $repo->insertActive($this->lesson($ctx, $ctx['section_id'], $ctx['teacher_id'], null)));
        // The caller's school context is back after the cross-school check.
        $this->assertSame((string) $ctx['school_id'], DB::selectOne("SELECT current_setting('app.current_school_id', true) AS v")->v);
        // Another day is free.
        $repo->insertActive($this->lesson($ctx, $ctx['section_id'], $ctx['teacher_id'], null, day: 3));
        $this->assertSame(1, DB::table('timetable.schedules')->where('school_id', $ctx['school_id'])->whereNull('cancelled_at')->count());
    }

    #[Test]
    public function slots_marked_unavailable_refuse_a_lesson(): void
    {
        $ctx = $this->seedScheduleGraph('UN');
        $repo = app(ScheduleRepositoryInterface::class);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school_id']]);
        $mark = fn (array $target) => DB::table('timetable.availability')->insert($target + [
            'school_id' => $ctx['school_id'], 'academic_year_id' => $ctx['year_id'], 'day_of_week' => 2, 'period_id' => $ctx['period_id'], 'kind' => 1, 'status' => 1,
        ]);

        $mark(['room_id' => $ctx['room_id']]);
        $this->assertConflict('timetable.room_unavailable_slot', fn () => $repo->insertActive($this->lesson($ctx, $ctx['section_id'], $ctx['teacher_id'], $ctx['room_id'])));
        $mark(['teacher_id' => $ctx['teacher_id']]);
        $this->assertConflict('timetable.teacher_unavailable_slot', fn () => $repo->insertActive($this->lesson($ctx, $ctx['section_id'], $ctx['teacher_id'], null)));
    }

    #[Test]
    public function a_substitute_teacher_busy_in_that_slot_is_refused(): void
    {
        $ctx = $this->seedScheduleGraph('SB');
        $classId = (int) DB::table('enrollment.sections')->where('id', $ctx['section_id'])->value('class_id');
        $sectionB = (int) $this->createSectionForClass($classId)->id;
        $teacherB = $this->addTeacher($ctx, 'SB-B');
        $repo = app(ScheduleRepositoryInterface::class);
        $first = $repo->insertActive($this->lesson($ctx, $ctx['section_id'], $ctx['teacher_id'], null));
        $repo->insertActive($this->lesson($ctx, $sectionB, $teacherB, null));

        $data = fn (?int $sub) => new PersistScheduleExceptionData(
            schoolId: $ctx['school_id'], scheduleId: $first, exceptionDate: '2026-10-12', substituteTeacherId: $sub, substituteRoomId: null,
            reason: null, at: now()->toIso8601String(), correlationId: null, createdBy: null,
        );
        $exceptions = app(ScheduleExceptionRepositoryInterface::class);
        try {
            $exceptions->insert($data($teacherB));
            $this->fail('Expected timetable.substitute_teacher_busy');
        } catch (ScheduleValidationException $e) {
            $this->assertSame('timetable.substitute_teacher_busy', $e->errorCode());
        }
    }

    /** @param  array{school_id:int, year_id:int, period_id:int, subject_id:int}  $ctx */
    private function lesson(array $ctx, int $sectionId, int $teacherId, ?int $roomId, ?int $activityId = null, int $day = 2, ?int $coTeacher = null): PersistScheduleData
    {
        return new PersistScheduleData(
            schoolId: $ctx['school_id'], sectionId: $sectionId, academicYearId: $ctx['year_id'], dayOfWeek: $day, periodId: $ctx['period_id'],
            subjectId: $ctx['subject_id'], teacherId: $teacherId, roomId: $roomId, at: now()->toIso8601String(), correlationId: null, createdBy: null,
            coTeacherId: $coTeacher, activityId: $activityId,
        );
    }

    private function assertConflict(string $code, callable $write): void
    {
        try {
            $write();
            $this->fail('Expected '.$code);
        } catch (ScheduleSlotConflictException $e) {
            $this->assertSame($code, $e->errorCode());
        }
    }

    /** @param  array{school_id:int, year_id:int, subject_id:int}  $ctx */
    private function addTeacher(array $ctx, string $tag): int
    {
        $id = (int) DB::table('teachers.teachers')->insertGetId([
            'employee_code' => 'T'.substr(uniqid($tag), -10), 'first_name' => 'T', 'last_name' => $tag, 'full_name' => 'T '.$tag,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('teachers.teacher_schools')->insert(['teacher_id' => $id, 'school_id' => $ctx['school_id'], 'academic_year_id' => $ctx['year_id'], 'is_primary' => true, 'created_at' => now()]);
        TimetableTeachingFixture::assignSubjectsToTeachers($ctx['school_id'], $ctx['year_id']);

        return $id;
    }

    /** @param  array{school_id:int}  $ctx */
    private function addWorkshop(array $ctx, string $code, ?int $roomId): int
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school_id']]);

        return (int) DB::table('vocational.workshops')->insertGetId([
            'school_id' => $ctx['school_id'], 'code' => $code, 'name' => 'Workshop '.$code, 'capacity' => 20, 'safety_capacity' => 15,
            'room_id' => $roomId, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param  array{school_id:int, year_id:int, subject_id:int}  $ctx */
    private function addActivity(array $ctx, int $workshopId): int
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school_id']]);

        return (int) DB::table('timetable.activities')->insertGetId([
            'school_id' => $ctx['school_id'], 'academic_year_id' => $ctx['year_id'], 'subject_id' => $ctx['subject_id'],
            'activity_type' => 1, 'weekly_count' => 1, 'workshop_id' => $workshopId, 'status' => 1,
        ]);
    }

    /**
     * @return array{
     *     school_id:int,
     *     year_id:int,
     *     section_id:int,
     *     period_id:int,
     *     subject_id:int,
     *     teacher_id:int,
     *     room_id:int
     * }
     */
    private function seedScheduleGraph(string $suffix): array
    {
        $schoolId = $this->createSchool('SCH-TV-'.$suffix, 'TV '.$suffix);
        $yearId = $this->createAcademicYear();
        $class = $this->createClassForSchool($schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $periodId = (int) DB::table(SchemaHelper::qualified('timetable', 'periods'))->insertGetId([
            'school_id' => $schoolId,
            'period_number' => 1,
            'start_time' => '08:00:00',
            'end_time' => '08:45:00',
            'period_type' => 1,
        ]);
        $subjectId = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'S'.substr(uniqid(), -8),
            'name' => 'Sub '.$suffix,
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $teacherId = (int) DB::table(SchemaHelper::qualified('teachers', 'teachers'))->insertGetId([
            'employee_code' => 'T'.substr(uniqid($suffix), -10),
            'first_name' => 'T',
            'last_name' => $suffix,
            'full_name' => 'T '.$suffix,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))->insert([
            'teacher_id' => $teacherId,
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'is_primary' => true,
            'created_at' => now(),
        ]);

        $branchId = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
            'school_id' => $schoolId,
            'code' => 'BR'.$suffix,
            'name' => 'Branch',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $roomId = (int) DB::table(SchemaHelper::qualified('organization', 'rooms'))->insertGetId([
            'branch_id' => $branchId,
            'code' => 'RM'.$suffix,
            'name' => 'Room',
            'room_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TimetableTeachingFixture::assignSubjectsToTeachers($schoolId, $yearId);

        return [
            'school_id' => $schoolId,
            'year_id' => $yearId,
            'section_id' => (int) $section->id,
            'period_id' => $periodId,
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,
            'room_id' => $roomId,
        ];
    }
}
