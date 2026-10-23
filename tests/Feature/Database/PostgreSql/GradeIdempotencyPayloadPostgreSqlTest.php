<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

/**
 * EXAMIS U9 — a grade command's idempotency key identifies one request: replaying it with a different payload is refused
 * (409 grades.idempotency_payload_conflict) instead of silently returning the first result; the same request replays.
 */
final class GradeIdempotencyPayloadPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    /** @return array<string, mixed> */
    private function scene(): array
    {
        $school = $this->createSchool('SCH-IDEM', 'Idempotency');
        $g = $this->seedExamGradeGraph($school, suffix: 'IDEM');
        $this->markSessionInProgressForGradeEntry($g['session_id']);
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantGradesManager($user, $school);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $school);

        return $g + ['school' => $school, 'user' => $user];
    }

    private function grades(int $seat): int
    {
        return DB::table(SchemaHelper::qualified('exams', 'student_grades'))->where('exam_enrollment_id', $seat)->count();
    }

    #[Test]
    public function entering_with_a_reused_key_and_another_score_is_refused(): void
    {
        $s = $this->scene();
        $body = ['exam_enrollment_id' => $s['exam_enrollment_id'], 'is_absent' => false];

        $first = $this->postJson('/api/v1/grades', $body + ['score' => 70], ['X-Idempotency-Key' => 'idem-enter'])->assertCreated();
        $this->postJson('/api/v1/grades', $body + ['score' => '70.00'], ['X-Idempotency-Key' => 'idem-enter'])
            ->assertOk()->assertJsonPath('data.id', $first->json('data.id'))->assertJsonPath('meta.from_idempotency_cache', true);
        $this->postJson('/api/v1/grades', $body + ['score' => 95], ['X-Idempotency-Key' => 'idem-enter'])
            ->assertStatus(409)->assertJsonPath('error_code', 'grades.idempotency_payload_conflict');

        $this->assertSame(1, $this->grades($s['exam_enrollment_id']));
        $this->assertSame('70.00', (string) DB::table(SchemaHelper::qualified('exams', 'student_grades'))->where('exam_enrollment_id', $s['exam_enrollment_id'])->value('score'));
    }

    #[Test]
    public function correct_void_and_finalize_refuse_a_reused_key_with_another_payload(): void
    {
        $s = $this->scene();
        $grade = (int) $this->postJson('/api/v1/grades', ['exam_enrollment_id' => $s['exam_enrollment_id'], 'score' => 60, 'is_absent' => false], ['X-Idempotency-Key' => 'idem-seed'])->json('data.id');
        $year = ['academic_year_id' => $s['year_id']];

        $corrected = (int) $this->postJson("/api/v1/grades/{$grade}/correct", $year + ['score' => 65, 'is_absent' => false, 'reason' => 'typo'], ['X-Idempotency-Key' => 'idem-correct'])
            ->assertSuccessful()->json('data.id');
        $this->postJson("/api/v1/grades/{$grade}/correct", $year + ['score' => 99, 'is_absent' => false, 'reason' => 'typo'], ['X-Idempotency-Key' => 'idem-correct'])
            ->assertStatus(409)->assertJsonPath('error_code', 'grades.idempotency_payload_conflict');

        $this->postJson("/api/v1/grades/{$corrected}/finalize", $year, ['X-Idempotency-Key' => 'idem-final'])->assertSuccessful();
        $this->postJson("/api/v1/grades/{$grade}/finalize", $year, ['X-Idempotency-Key' => 'idem-final'])
            ->assertStatus(409)->assertJsonPath('error_code', 'grades.idempotency_payload_conflict');

        $this->postJson("/api/v1/grades/{$corrected}/void", $year + ['reason' => 'withdrawn'], ['X-Idempotency-Key' => 'idem-void'])->assertSuccessful();
        $this->postJson("/api/v1/grades/{$corrected}/void", $year + ['reason' => 'another reason'], ['X-Idempotency-Key' => 'idem-void'])
            ->assertStatus(409)->assertJsonPath('error_code', 'grades.idempotency_payload_conflict');

        $this->assertSame(2, $this->grades($s['exam_enrollment_id']), 'no extra grade rows');
    }

    #[Test]
    public function an_entry_stored_before_fingerprints_still_replays(): void
    {
        $s = $this->scene();
        $grade = (int) $this->postJson('/api/v1/grades', ['exam_enrollment_id' => $s['exam_enrollment_id'], 'score' => 55, 'is_absent' => false], ['X-Idempotency-Key' => 'idem-legacy'])->json('data.id');
        // Simulate a key stored by the previous version (no fingerprint in the payload).
        DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))->where('key', 'idem-legacy')->where('command_name', 'EnterStudentGrade')
            ->update(['response_payload' => json_encode(['grade_id' => $grade, 'academic_year_id' => $s['year_id']])]);

        $this->postJson('/api/v1/grades', ['exam_enrollment_id' => $s['exam_enrollment_id'], 'score' => 56, 'is_absent' => false], ['X-Idempotency-Key' => 'idem-legacy'])
            ->assertOk()->assertJsonPath('data.id', $grade);
    }

    #[Test]
    public function the_grade_sheet_reports_an_edited_row_resent_with_the_same_key(): void
    {
        $s = $this->scene();
        $this->actingAs($s['user']);
        $this->withSession(['current_school_id' => $s['school']]);
        $url = "/grades/sheet?academic_year_id={$s['year_id']}&session_id={$s['session_id']}";
        $row = fn (string $score): array => ['rows' => [['exam_enrollment_id' => $s['exam_enrollment_id'], 'score' => $score, 'is_absent' => false]]];

        $this->from($url)->post("/grades/sheet/{$s['session_id']}", $row('70'), ['X-Idempotency-Key' => 'idem-sheet']);
        $edited = $this->from($url)->post("/grades/sheet/{$s['session_id']}", $row('80'), ['X-Idempotency-Key' => 'idem-sheet']);

        $this->assertSame('grades.idempotency_payload_conflict', $edited->getSession()->get('grade_sheet_outcomes')[0]['status']);
        $this->assertSame(1, $this->grades($s['exam_enrollment_id']));
    }
}
