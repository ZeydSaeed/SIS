<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

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
