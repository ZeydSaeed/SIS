<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Timetable\ValueObjects\ScheduleLifecycleStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvScheduleReactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_reactivate_cancelled_schedule(): void
    {
        $ctx = $this->seedScheduleGraph('REACT');
        $this->actingAsTimetableManagerForSchool($ctx['school_id']);

        $scheduleId = (int) $this->postJson('/api/v1/timetable/schedules', [
            'academic_year_id' => $ctx['year_id'],
            'section_id' => $ctx['section_id'],
            'day_of_week' => 2,
            'period_id' => $ctx['period_id'],
            'subject_id' => $ctx['subject_id'],
            'teacher_id' => $ctx['teacher_id'],
            'room_id' => $ctx['room_id'],
        ], ['X-Idempotency-Key' => 'tv-react-create'])->json('data.id');

        $this->postJson("/api/v1/timetable/schedules/{$scheduleId}/cancel", [], [
            'X-Idempotency-Key' => 'tv-react-cancel',
        ])->assertOk();

        $reactivate = $this->postJson("/api/v1/timetable/schedules/{$scheduleId}/reactivate", [], [
            'X-Idempotency-Key' => 'tv-react-on',
        ])->assertOk();

        $this->assertSame($scheduleId, (int) $reactivate->json('data.id'));

        $this->assertDatabaseHas(SchemaHelper::qualified('timetable', 'schedules'), [
            'id' => $scheduleId,
            'lifecycle_status' => ScheduleLifecycleStatus::Active->value,
            'cancelled_at' => null,
        ]);
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
