<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Timetable\Commands\CancelScheduleCommand;
use App\Application\Timetable\Commands\CancelScheduleHandler;
use App\Application\Timetable\Commands\CreateScheduleCommand;
use App\Application\Timetable\Commands\CreateScheduleHandler;
use App\Application\Timetable\Commands\UpdateScheduleCommand;
use App\Application\Timetable\Commands\UpdateScheduleHandler;
use App\Database\SchemaHelper;
use App\Domain\Timetable\Exceptions\ScheduleValidationException;
use App\Domain\Timetable\ValueObjects\ScheduleLifecycleStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvScheduleCommandsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function create_update_cancel_schedule_lifecycle(): void
    {
        $ctx = $this->seedScheduleContext('U04A');
        $userId = (int) $this->actingAsGradesManagerForSchool($ctx['school_id'])->id;
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school_id']]);

        $created = $this->app->make(CreateScheduleHandler::class)->handle(new CreateScheduleCommand(
            schoolId: $ctx['school_id'],
            sectionId: $ctx['section_id'],
            academicYearId: $ctx['year_id'],
            dayOfWeek: 1,
            periodId: $ctx['period_id'],
            subjectId: $ctx['subject_id'],
            teacherId: $ctx['teacher_id'],
            idempotencyKey: 'tv-u04-create',
            roomId: $ctx['room_id'],
            createdBy: $userId,
        ));

        $this->assertTrue($created->success);
        $this->assertDatabaseHas(SchemaHelper::qualified('timetable', 'schedules'), [
            'id' => $created->scheduleId,
            'lifecycle_status' => ScheduleLifecycleStatus::Active->value,
            'day_of_week' => 1,
        ]);

        $updated = $this->app->make(UpdateScheduleHandler::class)->handle(new UpdateScheduleCommand(
            schoolId: $ctx['school_id'],
            scheduleId: (int) $created->scheduleId,
            sectionId: $ctx['section_id'],
            academicYearId: $ctx['year_id'],
            dayOfWeek: 2,
            periodId: $ctx['period_id'],
            subjectId: $ctx['subject_id'],
            teacherId: $ctx['teacher_id'],
            idempotencyKey: 'tv-u04-update',
            roomId: $ctx['room_id'],
            createdBy: $userId,
        ));
        $this->assertTrue($updated->success);
        $this->assertDatabaseHas(SchemaHelper::qualified('timetable', 'schedules'), [
            'id' => $created->scheduleId,
            'day_of_week' => 2,
        ]);

        $cancelled = $this->app->make(CancelScheduleHandler::class)->handle(new CancelScheduleCommand(
            schoolId: $ctx['school_id'],
            scheduleId: (int) $created->scheduleId,
            idempotencyKey: 'tv-u04-cancel',
            cancelledBy: $userId,
        ));
        $this->assertTrue($cancelled->success);
        $this->assertDatabaseHas(SchemaHelper::qualified('timetable', 'schedules'), [
            'id' => $created->scheduleId,
            'lifecycle_status' => ScheduleLifecycleStatus::Cancelled->value,
        ]);
    }

    #[Test]
    public function create_rejects_teacher_not_assigned_to_school_year(): void
    {
        $ctx = $this->seedScheduleContext('U04B');
        $otherSchool = $this->createSchool('SCH-TV-U04X', 'Other');
        $foreignTeacher = (int) DB::table(SchemaHelper::qualified('teachers', 'teachers'))->insertGetId([
            'employee_code' => 'TX'.substr(uniqid(), -10),
            'first_name' => 'Foreign',
            'last_name' => 'Teacher',
            'full_name' => 'Foreign Teacher',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))->insert([
            'teacher_id' => $foreignTeacher,
            'school_id' => $otherSchool,
            'academic_year_id' => $ctx['year_id'],
            'is_primary' => true,
            'created_at' => now(),
        ]);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school_id']]);

        $this->expectException(ScheduleValidationException::class);
        $this->app->make(CreateScheduleHandler::class)->handle(new CreateScheduleCommand(
            schoolId: $ctx['school_id'],
            sectionId: $ctx['section_id'],
            academicYearId: $ctx['year_id'],
            dayOfWeek: 3,
            periodId: $ctx['period_id'],
            subjectId: $ctx['subject_id'],
            teacherId: $foreignTeacher,
            idempotencyKey: 'tv-u04-bad-teacher',
        ));
    }

    /**
     * @return array{school_id:int,year_id:int,section_id:int,period_id:int,subject_id:int,teacher_id:int,room_id:int}
     */
    private function seedScheduleContext(string $suffix): array
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
            'name' => 'Subject '.$suffix,
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $teacherId = (int) DB::table(SchemaHelper::qualified('teachers', 'teachers'))->insertGetId([
            'employee_code' => 'T'.substr(uniqid(), -10),
            'first_name' => 'Teach',
            'last_name' => $suffix,
            'full_name' => 'Teach '.$suffix,
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
            'code' => 'BR-'.$suffix,
            'name' => 'Branch '.$suffix,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $roomId = (int) DB::table(SchemaHelper::qualified('organization', 'rooms'))->insertGetId([
            'branch_id' => $branchId,
            'code' => 'RM-'.$suffix,
            'name' => 'Room '.$suffix,
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
            'room_id' => $roomId,
        ];
    }
}
