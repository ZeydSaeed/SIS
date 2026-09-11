<?php

namespace Tests\Feature\Security;

use App\Domain\Attendance\ValueObjects\AttendanceRecordStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Attendance\SeedsApplicationAttendanceGraph;
use Tests\TestCase;

class AttendanceApiAuthorizationTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationAttendanceGraph;

    #[Test]
    public function viewer_cannot_create_or_mark(): void
    {
        $schoolId = $this->createSchool('SCH-AV', 'School AV');
        $graph = $this->seedAttendanceGraph($schoolId, 'V');
        $this->actingAsAttendanceViewer(schoolId: $schoolId);

        $this->postJson('/api/v1/attendance/sessions', $this->createSessionPayload($graph))
            ->assertForbidden();

        $this->actingAsAttendanceTeacher(schoolId: $schoolId);
        $create = $this->postJson('/api/v1/attendance/sessions', $this->createSessionPayload($graph), [
            'X-Idempotency-Key' => 'authz-create-v',
        ]);
        $create->assertCreated();
        $sessionId = (int) $create->json('data.id');

        $this->actingAsAttendanceViewer(schoolId: $schoolId);
        $this->postJson("/api/v1/attendance/sessions/{$sessionId}/marks", [
            'academic_year_id' => $graph['year_id'],
            'records' => [[
                'student_id' => $graph['student_id'],
                'enrollment_id' => $graph['enrollment_id'],
                'status' => AttendanceRecordStatus::Present->value,
            ]],
        ], ['X-Idempotency-Key' => 'authz-mark-v'])
            ->assertForbidden();
    }

    #[Test]
    public function teacher_can_mark_but_cannot_correct(): void
    {
        $schoolId = $this->createSchool('SCH-AT', 'School AT');
        $graph = $this->seedAttendanceGraph($schoolId, 'T');
        $this->actingAsAttendanceTeacher(schoolId: $schoolId);

        $sessionId = (int) $this->postJson(
            '/api/v1/attendance/sessions',
            $this->createSessionPayload($graph),
            ['X-Idempotency-Key' => 'authz-create-t'],
        )->assertCreated()->json('data.id');

        $this->postJson("/api/v1/attendance/sessions/{$sessionId}/marks", [
            'academic_year_id' => $graph['year_id'],
            'records' => [[
                'student_id' => $graph['student_id'],
                'enrollment_id' => $graph['enrollment_id'],
                'status' => AttendanceRecordStatus::Present->value,
            ]],
        ], ['X-Idempotency-Key' => 'authz-mark-t'])->assertOk();

        $this->postJson(
            "/api/v1/attendance/sessions/{$sessionId}/students/{$graph['student_id']}/correct",
            [
                'academic_year_id' => $graph['year_id'],
                'new_status' => AttendanceRecordStatus::Absent->value,
                'reason' => 'Teacher cannot correct',
            ],
            ['X-Idempotency-Key' => 'authz-correct-t'],
        )->assertForbidden();
    }

    #[Test]
    public function manager_can_correct(): void
    {
        $schoolId = $this->createSchool('SCH-AM', 'School AM');
        $graph = $this->seedAttendanceGraph($schoolId, 'M');
        $this->actingAsAttendanceManagerForSchool($schoolId);

        $sessionId = (int) $this->postJson(
            '/api/v1/attendance/sessions',
            $this->createSessionPayload($graph),
            ['X-Idempotency-Key' => 'authz-create-m'],
        )->assertCreated()->json('data.id');

        $this->postJson("/api/v1/attendance/sessions/{$sessionId}/marks", [
            'academic_year_id' => $graph['year_id'],
            'records' => [[
                'student_id' => $graph['student_id'],
                'enrollment_id' => $graph['enrollment_id'],
                'status' => AttendanceRecordStatus::Present->value,
            ]],
        ], ['X-Idempotency-Key' => 'authz-mark-m'])->assertOk();

        $this->postJson(
            "/api/v1/attendance/sessions/{$sessionId}/students/{$graph['student_id']}/correct",
            [
                'academic_year_id' => $graph['year_id'],
                'new_status' => AttendanceRecordStatus::Late->value,
                'reason' => 'Manager correction',
            ],
            ['X-Idempotency-Key' => 'authz-correct-m'],
        )->assertOk()
            ->assertJsonPath('data.new_status', AttendanceRecordStatus::Late->value);
    }

    #[Test]
    public function cross_school_create_is_rejected(): void
    {
        $schoolA = $this->createSchool('SCH-AA1', 'School AA1');
        $schoolB = $this->createSchool('SCH-AB1', 'School AB1');
        $graphB = $this->seedAttendanceGraph($schoolB, 'XB');

        $this->actingAsAttendanceManagerForSchool($schoolA);

        $this->postJson('/api/v1/attendance/sessions', $this->createSessionPayload($graphB))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'attendance.cross_school_access');
    }

    #[Test]
    public function cross_school_show_session_is_not_found(): void
    {
        $schoolA = $this->createSchool('SCH-AA2', 'School AA2');
        $schoolB = $this->createSchool('SCH-AB2', 'School AB2');
        $graphB = $this->seedAttendanceGraph($schoolB, 'XB2');

        $this->actingAsAttendanceManagerForSchool($schoolB);
        $sessionId = (int) $this->postJson(
            '/api/v1/attendance/sessions',
            $this->createSessionPayload($graphB),
            ['X-Idempotency-Key' => 'authz-xb-create'],
        )->assertCreated()->json('data.id');

        $this->actingAsAttendanceManagerForSchool($schoolA);
        $this->getJson("/api/v1/attendance/sessions/{$sessionId}")
            ->assertNotFound();
    }

    #[Test]
    public function teacher_cannot_cancel_manager_can(): void
    {
        $schoolId = $this->createSchool('SCH-AC', 'School AC');
        $graph = $this->seedAttendanceGraph($schoolId, 'C');
        $this->actingAsAttendanceTeacher(schoolId: $schoolId);

        $sessionId = (int) $this->postJson(
            '/api/v1/attendance/sessions',
            $this->createSessionPayload($graph),
            ['X-Idempotency-Key' => 'authz-cancel-create'],
        )->assertCreated()->json('data.id');

        $this->postJson(
            "/api/v1/attendance/sessions/{$sessionId}/cancel",
            ['reason' => 'Teacher cannot cancel'],
            ['X-Idempotency-Key' => 'authz-cancel-t'],
        )->assertForbidden();

        $this->actingAsAttendanceManagerForSchool($schoolId);
        $this->postJson(
            "/api/v1/attendance/sessions/{$sessionId}/cancel",
            ['reason' => 'Manager cancels'],
            ['X-Idempotency-Key' => 'authz-cancel-m'],
        )->assertOk()
            ->assertJsonPath('data.new_status', 3);
    }

    #[Test]
    public function unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/v1/attendance/sessions', [
            'academic_year_id' => 1,
            'section_id' => 1,
            'subject_id' => 1,
            'session_date' => '2026-10-15',
            'teacher_id' => 1,
        ])->assertUnauthorized();
    }
}
