<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Timetable\Commands\CreateScheduleCommand;
use App\Application\Timetable\Commands\CreateScheduleHandler;
use App\Application\Vocational\Commands\CreateSpecializationCommand;
use App\Application\Vocational\Commands\CreateSpecializationHandler;
use App\Application\Vocational\Commands\CreateTrackCommand;
use App\Application\Vocational\Commands\CreateTrackHandler;
use App\Database\SchemaHelper;
use App\Security\Authorization\Permission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvReadHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function view_permissions_are_registered_on_manager_roles(): void
    {
        $this->assertArrayHasKey(Permission::TIMETABLE_VIEW, config('security.permissions'));
        $this->assertArrayHasKey(Permission::VOCATIONAL_VIEW, config('security.permissions'));
        $this->assertContains(Permission::TIMETABLE_VIEW, config('security.roles.timetable_manager'));
        $this->assertContains(Permission::VOCATIONAL_VIEW, config('security.roles.vocational_manager'));
    }

    #[Test]
    public function manager_can_list_and_show_schedules(): void
    {
        $ctx = $this->seedScheduleContext('R10');
        $this->actingAsTimetableManagerForSchool($ctx['school_id']);

        $created = $this->app->make(CreateScheduleHandler::class)->handle(new CreateScheduleCommand(
            schoolId: $ctx['school_id'],
            sectionId: $ctx['section_id'],
            academicYearId: $ctx['year_id'],
            dayOfWeek: 2,
            periodId: $ctx['period_id'],
            subjectId: $ctx['subject_id'],
            teacherId: $ctx['teacher_id'],
            idempotencyKey: 'tv-r10-sched',
            roomId: $ctx['room_id'],
        ));

        $list = $this->getJson(sprintf(
            '/api/v1/timetable/schedules?academic_year_id=%d',
            $ctx['year_id'],
        ))->assertOk();

        $this->assertGreaterThanOrEqual(1, (int) $list->json('meta.pagination.total'));
        $this->assertSame($created->scheduleId, (int) $list->json('data.0.id'));

        $this->getJson('/api/v1/timetable/schedules/'.$created->scheduleId)
            ->assertOk()
            ->assertJsonPath('data.id', $created->scheduleId)
            ->assertJsonPath('data.day_of_week', 2);
    }

    #[Test]
    public function manager_can_list_and_show_specializations(): void
    {
        $schoolId = $this->createSchool('SCH-TV-VR', 'Voc Read');
        $this->actingAsVocationalManagerForSchool($schoolId);

        $spec = $this->app->make(CreateSpecializationHandler::class)->handle(new CreateSpecializationCommand(
            schoolId: $schoolId,
            code: 'MECH',
            name: 'Mechanics',
            idempotencyKey: 'tv-r10-spec',
            description: 'Mech family',
        ));

        $this->app->make(CreateTrackHandler::class)->handle(new CreateTrackCommand(
            schoolId: $schoolId,
            specializationId: (int) $spec->specializationId,
            code: 'M1',
            name: 'Mech 1',
            idempotencyKey: 'tv-r10-track',
        ));

        $this->getJson('/api/v1/vocational/specializations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $spec->specializationId)
            ->assertJsonPath('meta.pagination.total', 1);

        $this->getJson('/api/v1/vocational/specializations/'.$spec->specializationId)
            ->assertOk()
            ->assertJsonPath('data.code', 'MECH')
            ->assertJsonPath('data.tracks.0.code', 'M1');
    }

    #[Test]
    public function unauthorized_user_cannot_list_schedules_or_specializations(): void
    {
        $schoolId = $this->createSchool('SCH-TV-RD', 'Read Deny');
        $this->actingAsAttendanceViewer(schoolId: $schoolId);

        $this->getJson('/api/v1/timetable/schedules?academic_year_id=1')->assertForbidden();
        $this->getJson('/api/v1/vocational/specializations')->assertForbidden();
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
            'name' => 'Sub '.$suffix,
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $teacherId = (int) DB::table(SchemaHelper::qualified('teachers', 'teachers'))->insertGetId([
            'employee_code' => 'T'.substr(uniqid(), -10),
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
