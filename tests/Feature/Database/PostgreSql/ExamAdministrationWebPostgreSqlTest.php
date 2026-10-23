<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Infrastructure\Persistence\Eloquent\SecurityAuditLogRecord;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

/**
 * EXAMIS U8 — exam administration from the web: create an exam and its sessions, open and close a session, through the
 * same commands, requests and permissions as the API.
 */
final class ExamAdministrationWebPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    /** @return array<string, mixed> */
    private function scene(): array
    {
        $school = $this->createSchool('SCH-EXADM', 'Exam admin');
        $g = $this->seedExamGradeGraph($school, suffix: 'EXADM');
        $typeId = (int) DB::table(SchemaHelper::qualified('exams', 'exams'))->where('id', $g['exam_id'])->value('exam_type_id');
        $grade = $this->createGradeLevel('G-EXADM');
        $this->createCurriculumWithSubjects($school, $g['year_id'], $grade, [$g['subject_id']]);

        return $g + ['school' => $school, 'type_id' => $typeId];
    }

    private function as(string $role, int $school): User
    {
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->{'grant'.$role}($user, $school);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $school]);

        return $user;
    }

    /** @param  array<string, mixed>  $s  @return array<string, mixed> */
    private function examPayload(array $s, array $overrides = []): array
    {
        return $overrides + [
            'academic_year_id' => $s['year_id'], 'term_id' => $s['term_id'], 'exam_type_id' => $s['type_id'],
            'name' => 'امتحان نصف السنة', 'start_date' => '2026-12-01', 'end_date' => '2026-12-10',
        ];
    }

    private function exams(int $school): int
    {
        return DB::table(SchemaHelper::qualified('exams', 'exams'))->where('school_id', $school)->count();
    }

    private function sessionStatus(int $session): int
    {
        return (int) DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))->where('id', $session)->value('status');
    }

    #[Test]
    public function the_manager_creates_an_exam_once_per_request(): void
    {
        $s = $this->scene();
        $user = $this->as('GradesManager', $s['school']);
        $before = $this->exams($s['school']);

        $first = $this->from('/exams')->post('/exams', $this->examPayload($s), ['X-Idempotency-Key' => 'web-exam-1']);
        $examId = (int) DB::table(SchemaHelper::qualified('exams', 'exams'))->where('school_id', $s['school'])->max('id');
        $first->assertRedirect("/exams/{$examId}")->assertSessionHas('success', 'flash.exams.created');
        $this->from('/exams')->post('/exams', $this->examPayload($s), ['X-Idempotency-Key' => 'web-exam-1'])->assertRedirect("/exams/{$examId}");

        $this->assertSame($before + 1, $this->exams($s['school']));
        $this->assertSame('امتحان نصف السنة', DB::table(SchemaHelper::qualified('exams', 'exams'))->where('id', $examId)->value('name'));
        $this->assertTrue(SecurityAuditLogRecord::query()->where('action', 'exams.web.store')->where('actor_id', $user->id)->exists());
    }

    #[Test]
    public function invalid_exams_are_refused_and_create_nothing(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);
        $before = $this->exams($s['school']);

        $this->from('/exams')->post('/exams', $this->examPayload($s, ['end_date' => '2026-11-01']), ['X-Idempotency-Key' => 'web-exam-dates'])
            ->assertRedirect('/exams')->assertSessionHas('error');
        $this->from('/exams')->post('/exams', $this->examPayload($s))->assertSessionHasErrors('X-Idempotency-Key');
        $this->from('/exams')->post('/exams', $this->examPayload($s, ['school_id' => 99]), ['X-Idempotency-Key' => 'web-exam-school'])->assertSessionHasErrors('school_id');

        $this->assertSame($before, $this->exams($s['school']));
    }

    #[Test]
    public function the_manager_adds_opens_and_closes_a_session(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);

        $this->from("/exams/{$s['exam_id']}")->post("/exams/{$s['exam_id']}/sessions", [
            'subject_id' => $s['subject_id'], 'session_date' => '2026-12-03', 'start_time' => '09:00:00', 'end_time' => '11:00:00',
            'max_grade' => 50, 'pass_grade' => 25,
        ], ['X-Idempotency-Key' => 'web-session-1'])->assertRedirect("/exams/{$s['exam_id']}")->assertSessionHas('success', 'flash.exams.sessionCreated');
        $session = (int) DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))->where('exam_id', $s['exam_id'])->max('id');
        $this->assertSame(ExamSessionStatus::Scheduled->value, $this->sessionStatus($session));
        $this->assertSame(50, (int) DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))->where('id', $session)->value('max_grade'));

        $this->from("/exams/{$s['exam_id']}")->post("/exams/{$s['exam_id']}/sessions/{$session}/open", [], ['X-Idempotency-Key' => 'web-open-1'])
            ->assertSessionHas('success', 'flash.exams.sessionOpened');
        $this->assertSame(ExamSessionStatus::InProgress->value, $this->sessionStatus($session));

        $this->from("/exams/{$s['exam_id']}")->post("/exams/{$s['exam_id']}/sessions/{$session}/close", [], ['X-Idempotency-Key' => 'web-close-1'])
            ->assertSessionHas('success', 'flash.exams.sessionClosed');
        $this->assertSame(ExamSessionStatus::Completed->value, $this->sessionStatus($session));

        // A completed session cannot be opened again (the lifecycle guard refuses; nothing changes).
        $this->from("/exams/{$s['exam_id']}")->post("/exams/{$s['exam_id']}/sessions/{$session}/open", [], ['X-Idempotency-Key' => 'web-open-2'])->assertSessionHas('error');
        $this->assertSame(ExamSessionStatus::Completed->value, $this->sessionStatus($session));
    }

    #[Test]
    public function a_session_under_another_exam_or_school_is_not_found(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);
        $other = $this->seedExamGradeGraph($s['school'], $s['year_id'], 'EXADM2');
        $foreignSchool = $this->createSchool('SCH-EXADM-F', 'Foreign');
        $foreign = $this->seedExamGradeGraph($foreignSchool, $s['year_id'], 'EXADMF');

        $this->post("/exams/{$s['exam_id']}/sessions/{$other['session_id']}/open", [], ['X-Idempotency-Key' => 'web-open-x'])->assertNotFound();
        $this->post("/exams/{$foreign['exam_id']}/sessions/{$foreign['session_id']}/open", [], ['X-Idempotency-Key' => 'web-open-f'])->assertNotFound();
        $this->assertSame(ExamSessionStatus::Scheduled->value, $this->sessionStatus($other['session_id']));
        $this->assertSame(ExamSessionStatus::Scheduled->value, $this->sessionStatus($foreign['session_id']));
    }

    #[Test]
    public function users_without_exam_administration_rights_are_refused(): void
    {
        $s = $this->scene();
        foreach (['GradesTeacher', 'GradesViewer'] as $role) {
            $this->as($role, $s['school']);
            $before = $this->exams($s['school']);
            $this->post('/exams', $this->examPayload($s), ['X-Idempotency-Key' => "web-{$role}-exam"])->assertForbidden();
            $this->post("/exams/{$s['exam_id']}/sessions", ['subject_id' => $s['subject_id'], 'session_date' => '2026-12-03', 'start_time' => '09:00:00', 'end_time' => '10:00:00'], ['X-Idempotency-Key' => "web-{$role}-session"])->assertForbidden();
            $this->post("/exams/{$s['exam_id']}/sessions/{$s['session_id']}/open", [], ['X-Idempotency-Key' => "web-{$role}-open"])->assertForbidden();
            $this->assertSame($before, $this->exams($s['school']), $role);
            $this->assertSame(ExamSessionStatus::Scheduled->value, $this->sessionStatus($s['session_id']), $role);
        }
    }

    #[Test]
    public function the_pages_offer_the_forms_only_to_users_who_may_use_them(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);
        $this->get('/exams?academic_year_id='.$s['year_id'])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('admin.can_create', true)
            ->where('admin.terms', fn ($terms) => collect($terms)->pluck('id')->contains($s['term_id']))
            ->where('admin.exam_types', fn ($types) => collect($types)->pluck('id')->contains($s['type_id'])));
        $this->get("/exams/{$s['exam_id']}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('admin.can_create_session', true)
            ->where('admin.can_open', true)
            ->where('admin.can_close', true)
            ->where('admin.subjects', fn ($subjects) => collect($subjects)->pluck('id')->all() === [$s['subject_id']]));

        $this->as('GradesTeacher', $s['school']);
        $this->get('/exams?academic_year_id='.$s['year_id'])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('admin.can_create', false)->where('admin.exam_types', []));
        $this->get("/exams/{$s['exam_id']}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('admin.can_create_session', false)->where('admin.can_open', false)->where('admin.subjects', []));
    }
}
