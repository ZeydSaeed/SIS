<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Timetable\ValueObjects\ScheduleLifecycleStatus;
use App\Security\Authorization\Permission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvScheduleHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function timetable_permissions_are_registered_for_manager_role(): void
    {
        $required = [
            Permission::TIMETABLE_SCHEDULE_CREATE,
            Permission::TIMETABLE_SCHEDULE_UPDATE,
            Permission::TIMETABLE_SCHEDULE_CANCEL,
            Permission::TIMETABLE_EXCEPTION_CREATE,
            Permission::TIMETABLE_EXCEPTION_UPDATE,
        ];

        foreach ($required as $permission) {
            $this->assertArrayHasKey($permission, config('security.permissions'));
            $this->assertContains($permission, Permission::all());
            $this->assertContains($permission, config('security.roles.timetable_manager'));
        }
    }

    #[Test]
    public function manager_can_create_update_cancel_schedule_and_manage_exceptions(): void
    {
        $ctx = $this->seedScheduleGraph('HTTP');
        $this->actingAsTimetableManagerForSchool($ctx['school_id']);

        $payload = [
            'academic_year_id' => $ctx['year_id'],
            'section_id' => $ctx['section_id'],
            'day_of_week' => 1,
            'period_id' => $ctx['period_id'],
            'subject_id' => $ctx['subject_id'],
            'teacher_id' => $ctx['teacher_id'],
            'room_id' => $ctx['room_id'],
        ];

        $create = $this->postJson('/api/v1/timetable/schedules', $payload, [
            'X-Idempotency-Key' => 'tv-http-create',
        ])->assertCreated();

        $scheduleId = (int) $create->json('data.id');
        $this->assertFalse((bool) $create->json('meta.from_idempotency_cache'));
        $this->assertDatabaseHas(SchemaHelper::qualified('timetable', 'schedules'), [
            'id' => $scheduleId,
            'lifecycle_status' => ScheduleLifecycleStatus::Active->value,
            'day_of_week' => 1,
        ]);

        $this->postJson('/api/v1/timetable/schedules', $payload, [
            'X-Idempotency-Key' => 'tv-http-create',
        ])->assertOk()
            ->assertJsonPath('data.id', $scheduleId)
            ->assertJsonPath('meta.from_idempotency_cache', true);

        $this->patchJson("/api/v1/timetable/schedules/{$scheduleId}", [
            ...$payload,
            'day_of_week' => 3,
        ], [
            'X-Idempotency-Key' => 'tv-http-update',
        ])->assertOk()
            ->assertJsonPath('data.id', $scheduleId);

        $this->assertDatabaseHas(SchemaHelper::qualified('timetable', 'schedules'), [
            'id' => $scheduleId,
            'day_of_week' => 3,
        ]);

        $exception = $this->postJson("/api/v1/timetable/schedules/{$scheduleId}/exceptions", [
            'exception_date' => '2026-10-20',
            'substitute_teacher_id' => $ctx['sub_teacher_id'],
            'substitute_room_id' => $ctx['room_id'],
            'reason' => 'Cover',
        ], [
            'X-Idempotency-Key' => 'tv-http-ex-create',
        ])->assertCreated();

        $exceptionId = (int) $exception->json('data.id');

        $this->patchJson("/api/v1/timetable/schedule-exceptions/{$exceptionId}", [
            'schedule_id' => $scheduleId,
            'exception_date' => '2026-10-20',
            'substitute_teacher_id' => $ctx['sub_teacher_id'],
            'reason' => 'Updated cover',
        ], [
            'X-Idempotency-Key' => 'tv-http-ex-update',
        ])->assertOk()
            ->assertJsonPath('data.id', $exceptionId);

        $this->assertDatabaseHas(SchemaHelper::qualified('timetable', 'schedule_exceptions'), [
            'id' => $exceptionId,
            'reason' => 'Updated cover',
        ]);

        $this->postJson("/api/v1/timetable/schedules/{$scheduleId}/cancel", [], [
            'X-Idempotency-Key' => 'tv-http-cancel',
        ])->assertOk()
            ->assertJsonPath('data.id', $scheduleId);

        $this->assertDatabaseHas(SchemaHelper::qualified('timetable', 'schedules'), [
            'id' => $scheduleId,
            'lifecycle_status' => ScheduleLifecycleStatus::Cancelled->value,
        ]);
    }

    #[Test]
    public function unauthorized_user_cannot_create_schedule(): void
    {
        $ctx = $this->seedScheduleGraph('DENY');
        $this->actingAsAttendanceViewer(schoolId: $ctx['school_id']);

        $this->postJson('/api/v1/timetable/schedules', [
            'academic_year_id' => $ctx['year_id'],
            'section_id' => $ctx['section_id'],
            'day_of_week' => 1,
            'period_id' => $ctx['period_id'],
            'subject_id' => $ctx['subject_id'],
            'teacher_id' => $ctx['teacher_id'],
            'room_id' => $ctx['room_id'],
        ], [
            'X-Idempotency-Key' => 'tv-http-deny',
        ])->assertForbidden();
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
