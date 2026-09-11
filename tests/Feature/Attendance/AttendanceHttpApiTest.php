<?php

namespace Tests\Feature\Attendance;

use App\Domain\Attendance\ValueObjects\AttendanceRecordStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Attendance\SeedsApplicationAttendanceGraph;
use Tests\TestCase;

class AttendanceHttpApiTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationAttendanceGraph;

    #[Test]
    public function manager_can_create_mark_correct_close_and_query(): void
    {
        $schoolId = $this->createSchool('SCH-AH', 'School AH');
        $graph = $this->seedAttendanceGraph($schoolId, 'H');
        $this->actingAsAttendanceManagerForSchool($schoolId);

        $create = $this->postJson(
            '/api/v1/attendance/sessions',
            $this->createSessionPayload($graph),
            ['X-Idempotency-Key' => 'http-create-1'],
        )->assertCreated();

        $sessionId = (int) $create->json('data.id');
        $this->assertFalse((bool) $create->json('meta.from_idempotency_cache'));

        $this->postJson("/api/v1/attendance/sessions/{$sessionId}/marks", [
            'academic_year_id' => $graph['year_id'],
            'records' => [[
                'student_id' => $graph['student_id'],
                'enrollment_id' => $graph['enrollment_id'],
                'status' => AttendanceRecordStatus::Present->value,
            ]],
        ], ['X-Idempotency-Key' => 'http-mark-1'])
            ->assertOk()
            ->assertJsonPath('data.marked_count', 1);

        $this->postJson(
            "/api/v1/attendance/sessions/{$sessionId}/students/{$graph['student_id']}/correct",
            [
                'academic_year_id' => $graph['year_id'],
                'new_status' => AttendanceRecordStatus::Absent->value,
                'reason' => 'Fixed status',
            ],
            ['X-Idempotency-Key' => 'http-correct-1'],
        )->assertOk()
            ->assertJsonPath('data.previous_status', AttendanceRecordStatus::Present->value)
            ->assertJsonPath('data.new_status', AttendanceRecordStatus::Absent->value);

        $this->getJson("/api/v1/attendance/sessions/{$sessionId}?include_records=1")
            ->assertOk()
            ->assertJsonPath('data.id', $sessionId);

        $this->getJson('/api/v1/attendance/sessions?academic_year_id='.$graph['year_id'])
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);

        $this->getJson(
            "/api/v1/attendance/sections/{$graph['section_id']}?academic_year_id={$graph['year_id']}&date={$graph['session_date']}",
        )->assertOk()
            ->assertJsonPath('data.section_id', $graph['section_id']);

        $this->getJson(
            "/api/v1/attendance/students/{$graph['student_id']}?academic_year_id={$graph['year_id']}",
        )->assertOk()
            ->assertJsonStructure(['data', 'meta']);

        $this->getJson(
            "/api/v1/attendance/sections/{$graph['section_id']}/daily-summary?date={$graph['session_date']}",
        )->assertOk()
            ->assertJsonStructure(['data']);

        $this->postJson(
            "/api/v1/attendance/sessions/{$sessionId}/close",
            [],
            ['X-Idempotency-Key' => 'http-close-1'],
        )->assertOk()
            ->assertJsonPath('data.session_id', $sessionId);
    }

    #[Test]
    public function create_and_mark_are_idempotent_with_same_key(): void
    {
        $schoolId = $this->createSchool('SCH-AI', 'School AI');
        $graph = $this->seedAttendanceGraph($schoolId, 'I');
        $this->actingAsAttendanceManagerForSchool($schoolId);

        $first = $this->postJson(
            '/api/v1/attendance/sessions',
            $this->createSessionPayload($graph),
            ['X-Idempotency-Key' => 'idem-create'],
        )->assertCreated();

        $second = $this->postJson(
            '/api/v1/attendance/sessions',
            $this->createSessionPayload($graph),
            ['X-Idempotency-Key' => 'idem-create'],
        )->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('meta.from_idempotency_cache', true);

        $sessionId = (int) $first->json('data.id');
        $markBody = [
            'academic_year_id' => $graph['year_id'],
            'records' => [[
                'student_id' => $graph['student_id'],
                'enrollment_id' => $graph['enrollment_id'],
                'status' => AttendanceRecordStatus::Late->value,
            ]],
        ];

        $this->postJson(
            "/api/v1/attendance/sessions/{$sessionId}/marks",
            $markBody,
            ['X-Idempotency-Key' => 'idem-mark'],
        )->assertOk()
            ->assertJsonPath('meta.from_idempotency_cache', false);

        $this->postJson(
            "/api/v1/attendance/sessions/{$sessionId}/marks",
            $markBody,
            ['X-Idempotency-Key' => 'idem-mark'],
        )->assertOk()
            ->assertJsonPath('meta.from_idempotency_cache', true);
    }

    #[Test]
    public function validation_rejects_prohibited_and_invalid_inputs(): void
    {
        $schoolId = $this->createSchool('SCH-AV2', 'School AV2');
        $graph = $this->seedAttendanceGraph($schoolId, 'V2');
        $this->actingAsAttendanceManagerForSchool($schoolId);

        $this->postJson('/api/v1/attendance/sessions', $this->createSessionPayload($graph, [
            'school_id' => $schoolId,
        ]))->assertStatus(422);

        $this->postJson('/api/v1/attendance/sessions', $this->createSessionPayload($graph, [
            'status' => 1,
        ]))->assertStatus(422);

        $this->postJson('/api/v1/attendance/sessions', [
            'section_id' => $graph['section_id'],
        ])->assertStatus(422);

        $sessionId = (int) $this->postJson(
            '/api/v1/attendance/sessions',
            $this->createSessionPayload($graph),
            ['X-Idempotency-Key' => 'val-create'],
        )->assertCreated()->json('data.id');

        $this->postJson("/api/v1/attendance/sessions/{$sessionId}/marks", [
            'academic_year_id' => $graph['year_id'],
            'records' => [[
                'student_id' => $graph['student_id'],
                'enrollment_id' => $graph['enrollment_id'],
                'status' => AttendanceRecordStatus::Present->value,
            ]],
        ])->assertStatus(422);

        $this->postJson("/api/v1/attendance/sessions/{$sessionId}/marks", [
            'academic_year_id' => $graph['year_id'],
            'records' => [],
        ], ['X-Idempotency-Key' => 'val-empty'])->assertStatus(422);

        $tooMany = [];
        for ($i = 1; $i <= 501; $i++) {
            $tooMany[] = [
                'student_id' => $i,
                'enrollment_id' => $i,
                'status' => AttendanceRecordStatus::Present->value,
            ];
        }
        $this->postJson("/api/v1/attendance/sessions/{$sessionId}/marks", [
            'academic_year_id' => $graph['year_id'],
            'records' => $tooMany,
        ], ['X-Idempotency-Key' => 'val-500'])->assertStatus(422);

        $this->postJson("/api/v1/attendance/sessions/{$sessionId}/marks", [
            'academic_year_id' => $graph['year_id'],
            'records' => [
                [
                    'student_id' => $graph['student_id'],
                    'enrollment_id' => $graph['enrollment_id'],
                    'status' => AttendanceRecordStatus::Present->value,
                ],
                [
                    'student_id' => $graph['student_id'],
                    'enrollment_id' => $graph['enrollment_id'],
                    'status' => AttendanceRecordStatus::Absent->value,
                ],
            ],
        ], ['X-Idempotency-Key' => 'val-dup'])->assertStatus(422);

        $this->postJson(
            "/api/v1/attendance/sessions/{$sessionId}/students/{$graph['student_id']}/correct",
            [
                'academic_year_id' => $graph['year_id'],
                'new_status' => AttendanceRecordStatus::Absent->value,
            ],
            ['X-Idempotency-Key' => 'val-reason'],
        )->assertStatus(422);

        $this->getJson('/api/v1/attendance/sessions')->assertStatus(422);
        $this->getJson("/api/v1/attendance/students/{$graph['student_id']}")->assertStatus(422);
    }

    #[Test]
    public function mark_on_closed_session_returns_422_and_double_close_returns_409(): void
    {
        $schoolId = $this->createSchool('SCH-AE', 'School AE');
        $graph = $this->seedAttendanceGraph($schoolId, 'E');
        $this->actingAsAttendanceManagerForSchool($schoolId);

        $sessionId = (int) $this->postJson(
            '/api/v1/attendance/sessions',
            $this->createSessionPayload($graph),
            ['X-Idempotency-Key' => 'err-create'],
        )->assertCreated()->json('data.id');

        $this->postJson(
            "/api/v1/attendance/sessions/{$sessionId}/close",
            [],
            ['X-Idempotency-Key' => 'err-close-1'],
        )->assertOk();

        $this->postJson("/api/v1/attendance/sessions/{$sessionId}/marks", [
            'academic_year_id' => $graph['year_id'],
            'records' => [[
                'student_id' => $graph['student_id'],
                'enrollment_id' => $graph['enrollment_id'],
                'status' => AttendanceRecordStatus::Present->value,
            ]],
        ], ['X-Idempotency-Key' => 'err-mark-closed'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'attendance.session_not_open');

        $this->postJson("/api/v1/attendance/sessions/{$sessionId}/close")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'attendance.session_close_conflict');
    }

    #[Test]
    public function missing_session_returns_404(): void
    {
        $schoolId = $this->createSchool('SCH-AN', 'School AN');
        $this->actingAsAttendanceManagerForSchool($schoolId);

        $this->getJson('/api/v1/attendance/sessions/999999')->assertNotFound();
        $this->postJson('/api/v1/attendance/sessions/999999/marks', [
            'academic_year_id' => 1,
            'records' => [[
                'student_id' => 1,
                'enrollment_id' => 1,
                'status' => AttendanceRecordStatus::Present->value,
            ]],
        ], ['X-Idempotency-Key' => 'missing-mark'])->assertNotFound();
    }
}
