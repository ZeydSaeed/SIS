<?php

namespace Tests\Feature\Attendance;

use App\Database\SchemaHelper;
use App\Domain\Attendance\ValueObjects\SessionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Attendance\SeedsApplicationAttendanceGraph;
use Tests\TestCase;

final class CancelAttendanceSessionHttpTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationAttendanceGraph;

    #[Test]
    public function manager_can_cancel_open_session_with_idempotent_replay(): void
    {
        $schoolId = $this->createSchool('SCH-CN', 'School CN');
        $graph = $this->seedAttendanceGraph($schoolId, 'CN');
        $this->actingAsAttendanceManagerForSchool($schoolId);

        $sessionId = (int) $this->postJson(
            '/api/v1/attendance/sessions',
            $this->createSessionPayload($graph),
            ['X-Idempotency-Key' => 'cancel-http-create'],
        )->assertCreated()->json('data.id');

        $first = $this->postJson(
            "/api/v1/attendance/sessions/{$sessionId}/cancel",
            ['reason' => 'Created in error'],
            ['X-Idempotency-Key' => 'cancel-http-1'],
        )->assertOk();

        $this->assertSame($sessionId, (int) $first->json('data.session_id'));
        $this->assertSame(SessionStatus::Open->value, (int) $first->json('data.previous_status'));
        $this->assertSame(SessionStatus::Cancelled->value, (int) $first->json('data.new_status'));
        $this->assertFalse((bool) $first->json('meta.from_idempotency_cache'));

        $replay = $this->postJson(
            "/api/v1/attendance/sessions/{$sessionId}/cancel",
            ['reason' => 'Created in error'],
            ['X-Idempotency-Key' => 'cancel-http-1'],
        )->assertOk();

        $this->assertTrue((bool) $replay->json('meta.from_idempotency_cache'));
        $this->assertSame(
            SessionStatus::Cancelled->value,
            (int) DB::table(SchemaHelper::qualified('attendance', 'sessions'))->where('id', $sessionId)->value('status'),
        );
    }

    #[Test]
    public function teacher_and_viewer_cannot_cancel(): void
    {
        $schoolId = $this->createSchool('SCH-CT', 'School CT');
        $graph = $this->seedAttendanceGraph($schoolId, 'CT');
        $this->actingAsAttendanceTeacher(schoolId: $schoolId);

        $sessionId = (int) $this->postJson(
            '/api/v1/attendance/sessions',
            $this->createSessionPayload($graph),
            ['X-Idempotency-Key' => 'cancel-teacher-create'],
        )->assertCreated()->json('data.id');

        $this->postJson(
            "/api/v1/attendance/sessions/{$sessionId}/cancel",
            ['reason' => 'Teacher attempt'],
            ['X-Idempotency-Key' => 'cancel-teacher-1'],
        )->assertForbidden();

        $this->actingAsAttendanceViewer(schoolId: $schoolId);
        $this->postJson(
            "/api/v1/attendance/sessions/{$sessionId}/cancel",
            ['reason' => 'Viewer attempt'],
            ['X-Idempotency-Key' => 'cancel-viewer-1'],
        )->assertForbidden();
    }

    #[Test]
    public function empty_reason_and_missing_idempotency_are_rejected(): void
    {
        $schoolId = $this->createSchool('SCH-CV', 'School CV');
        $graph = $this->seedAttendanceGraph($schoolId, 'CV');
        $this->actingAsAttendanceManagerForSchool($schoolId);

        $sessionId = (int) $this->postJson(
            '/api/v1/attendance/sessions',
            $this->createSessionPayload($graph),
            ['X-Idempotency-Key' => 'cancel-val-create'],
        )->assertCreated()->json('data.id');

        $this->postJson(
            "/api/v1/attendance/sessions/{$sessionId}/cancel",
            ['reason' => '   '],
            ['X-Idempotency-Key' => 'cancel-val-1'],
        )->assertStatus(422);

        $this->postJson(
            "/api/v1/attendance/sessions/{$sessionId}/cancel",
            ['reason' => 'Valid reason'],
        )->assertStatus(422);
    }

    #[Test]
    public function cross_school_cancel_is_denied(): void
    {
        $schoolA = $this->createSchool('SCH-CXA', 'School CXA');
        $schoolB = $this->createSchool('SCH-CXB', 'School CXB');
        $graphB = $this->seedAttendanceGraph($schoolB, 'CX');

        $this->actingAsAttendanceManagerForSchool($schoolB);
        $sessionId = (int) $this->postJson(
            '/api/v1/attendance/sessions',
            $this->createSessionPayload($graphB),
            ['X-Idempotency-Key' => 'cancel-xb-create'],
        )->assertCreated()->json('data.id');

        $this->actingAsAttendanceManagerForSchool($schoolA);
        $response = $this->postJson(
            "/api/v1/attendance/sessions/{$sessionId}/cancel",
            ['reason' => 'Cross school'],
            ['X-Idempotency-Key' => 'cancel-xb-1'],
        );

        $this->assertTrue(in_array($response->status(), [403, 404, 422], true));
        if ($response->status() === 422) {
            $response->assertJsonPath('error_code', 'attendance.cross_school_access');
        }
    }

    #[Test]
    public function second_cancel_without_cache_returns_conflict(): void
    {
        $schoolId = $this->createSchool('SCH-CC', 'School CC');
        $graph = $this->seedAttendanceGraph($schoolId, 'CC');
        $this->actingAsAttendanceManagerForSchool($schoolId);

        $sessionId = (int) $this->postJson(
            '/api/v1/attendance/sessions',
            $this->createSessionPayload($graph),
            ['X-Idempotency-Key' => 'cancel-conf-create'],
        )->assertCreated()->json('data.id');

        $this->postJson(
            "/api/v1/attendance/sessions/{$sessionId}/cancel",
            ['reason' => 'First cancel'],
            ['X-Idempotency-Key' => 'cancel-conf-1'],
        )->assertOk();

        $this->postJson(
            "/api/v1/attendance/sessions/{$sessionId}/cancel",
            ['reason' => 'Second cancel'],
            ['X-Idempotency-Key' => 'cancel-conf-2'],
        )->assertStatus(409)
            ->assertJsonPath('error_code', 'attendance.session_cancel_conflict');
    }
}
