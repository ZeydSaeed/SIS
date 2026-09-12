<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Timetable\Commands\CreateScheduleCommand;
use App\Application\Timetable\Commands\CreateScheduleExceptionCommand;
use App\Application\Timetable\Commands\CreateScheduleExceptionHandler;
use App\Application\Timetable\Commands\CreateScheduleHandler;
use App\Application\Timetable\Commands\UpdateScheduleExceptionCommand;
use App\Application\Timetable\Commands\UpdateScheduleExceptionHandler;
use App\Database\SchemaHelper;
use App\Domain\Timetable\Exceptions\ScheduleExceptionConflictException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvScheduleExceptionCommandsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function create_and_update_schedule_exception(): void
    {
        $ctx = $this->seedScheduleGraph('EX1');
        $userId = (int) $this->actingAsGradesManagerForSchool($ctx['school_id'])->id;
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school_id']]);

        $schedule = $this->app->make(CreateScheduleHandler::class)->handle(new CreateScheduleCommand(
            schoolId: $ctx['school_id'],
            sectionId: $ctx['section_id'],
            academicYearId: $ctx['year_id'],
            dayOfWeek: 1,
            periodId: $ctx['period_id'],
            subjectId: $ctx['subject_id'],
            teacherId: $ctx['teacher_id'],
            idempotencyKey: 'ex1-sched',
            roomId: $ctx['room_id'],
            createdBy: $userId,
        ));

        $created = $this->app->make(CreateScheduleExceptionHandler::class)->handle(new CreateScheduleExceptionCommand(
            schoolId: $ctx['school_id'],
            scheduleId: (int) $schedule->scheduleId,
            exceptionDate: '2026-10-15',
            idempotencyKey: 'ex1-create',
            substituteTeacherId: $ctx['sub_teacher_id'],
            substituteRoomId: $ctx['room_id'],
            reason: 'Sick leave',
            createdBy: $userId,
        ));

        $this->assertTrue($created->success);
        $this->assertDatabaseHas(SchemaHelper::qualified('timetable', 'schedule_exceptions'), [
            'id' => $created->exceptionId,
            'exception_date' => '2026-10-15',
            'reason' => 'Sick leave',
        ]);

        $updated = $this->app->make(UpdateScheduleExceptionHandler::class)->handle(new UpdateScheduleExceptionCommand(
            schoolId: $ctx['school_id'],
            exceptionId: (int) $created->exceptionId,
            scheduleId: (int) $schedule->scheduleId,
            exceptionDate: '2026-10-15',
            idempotencyKey: 'ex1-update',
            substituteTeacherId: $ctx['sub_teacher_id'],
            reason: 'Training day',
            createdBy: $userId,
        ));
        $this->assertTrue($updated->success);
        $this->assertDatabaseHas(SchemaHelper::qualified('timetable', 'schedule_exceptions'), [
            'id' => $created->exceptionId,
            'reason' => 'Training day',
        ]);
    }

    #[Test]
    public function create_rejects_duplicate_schedule_date(): void
    {
        $ctx = $this->seedScheduleGraph('EX2');
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school_id']]);

        $schedule = $this->app->make(CreateScheduleHandler::class)->handle(new CreateScheduleCommand(
            schoolId: $ctx['school_id'],
            sectionId: $ctx['section_id'],
            academicYearId: $ctx['year_id'],
            dayOfWeek: 2,
            periodId: $ctx['period_id'],
            subjectId: $ctx['subject_id'],
            teacherId: $ctx['teacher_id'],
            idempotencyKey: 'ex2-sched',
            roomId: $ctx['room_id'],
        ));

        $this->app->make(CreateScheduleExceptionHandler::class)->handle(new CreateScheduleExceptionCommand(
            schoolId: $ctx['school_id'],
            scheduleId: (int) $schedule->scheduleId,
            exceptionDate: '2026-11-01',
            idempotencyKey: 'ex2-a',
            reason: 'A',
        ));

        $this->expectException(ScheduleExceptionConflictException::class);
        $this->app->make(CreateScheduleExceptionHandler::class)->handle(new CreateScheduleExceptionCommand(
            schoolId: $ctx['school_id'],
            scheduleId: (int) $schedule->scheduleId,
            exceptionDate: '2026-11-01',
            idempotencyKey: 'ex2-b',
            reason: 'B',
        ));
    }

    /**
     * @return array{school_id:int,year_id:int,section_id:int,period_id:int,subject_id:int,teacher_id:int,sub_teacher_id:int,room_id:int}
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

        $teacherId = $this->insertTeacher($schoolId, $yearId, 'A'.$suffix);
        $subTeacherId = $this->insertTeacher($schoolId, $yearId, 'B'.$suffix);

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

        return [
            'school_id' => $schoolId,
            'year_id' => $yearId,
            'section_id' => (int) $section->id,
            'period_id' => $periodId,
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,
            'sub_teacher_id' => $subTeacherId,
            'room_id' => $roomId,
        ];
    }

    private function insertTeacher(int $schoolId, int $yearId, string $tag): int
    {
        $id = (int) DB::table(SchemaHelper::qualified('teachers', 'teachers'))->insertGetId([
            'employee_code' => 'T'.substr(uniqid($tag), -10),
            'first_name' => 'T',
            'last_name' => $tag,
            'full_name' => 'T '.$tag,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))->insert([
            'teacher_id' => $id,
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'is_primary' => true,
            'created_at' => now(),
        ]);

        return $id;
    }
}
