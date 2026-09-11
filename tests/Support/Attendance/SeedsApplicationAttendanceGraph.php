<?php

namespace Tests\Support\Attendance;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;

/**
 * SchemaHelper-aware attendance graph for SQLite + PostgreSQL HTTP feature tests.
 */
trait SeedsApplicationAttendanceGraph
{
    /**
     * @return array{
     *   school_id:int,year_id:int,section_id:int,class_id:int,
     *   subject_id:int,teacher_id:int,period_id:int,
     *   student_id:int,enrollment_id:int,session_date:string
     * }
     */
    protected function seedAttendanceGraph(int $schoolId, string $suffix = 'A'): array
    {
        $yearId = $this->createAcademicYear('AY-ATT-'.$suffix);
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId);

        $subjectId = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'ASUB'.substr(uniqid(), -4),
            'name' => 'Attendance Subject '.$suffix,
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $teacherId = (int) DB::table(SchemaHelper::qualified('teachers', 'teachers'))->insertGetId([
            'employee_code' => 'AT'.substr(uniqid(), -4),
            'first_name' => 'Att',
            'last_name' => 'Teacher',
            'full_name' => 'Att Teacher',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $periodId = (int) DB::table(SchemaHelper::qualified('timetable', 'periods'))->insertGetId([
            'school_id' => $schoolId,
            'period_number' => (abs(crc32($suffix.uniqid('', true))) % 30000) + 1,
            'start_time' => '08:00:00',
            'end_time' => '08:45:00',
            'period_type' => 1,
        ]);

        return [
            'school_id' => $schoolId,
            'year_id' => $yearId,
            'section_id' => (int) $enrollment->section_id,
            'class_id' => (int) $enrollment->class_id,
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,
            'period_id' => $periodId,
            'student_id' => (int) $enrollment->student_id,
            'enrollment_id' => (int) $enrollment->id,
            'session_date' => '2026-10-15',
        ];
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array<string, mixed>
     */
    protected function createSessionPayload(array $graph, array $overrides = []): array
    {
        return array_merge([
            'academic_year_id' => $graph['year_id'],
            'section_id' => $graph['section_id'],
            'subject_id' => $graph['subject_id'],
            'session_date' => $graph['session_date'],
            'teacher_id' => $graph['teacher_id'],
            'period_id' => $graph['period_id'],
        ], $overrides);
    }
}
