<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

/**
 * EXAMIS 7.3a — «بنك الأسئلة» from the web: a teacher writes a draft of a subject they teach and sends it for review,
 * an approver (never the author) approves or rejects, approving supersedes the previous approved version, a question is
 * retired (never deleted). Teacher scope, four eyes, idempotency and another school's question are covered here.
 */
final class QuestionBankWebPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    /** @return array{school: int, year: int, subject: int, other_subject: int, branch: int} */
    private function scene(string $suffix = 'BANK'): array
    {
        $school = $this->createSchool('SCH-'.$suffix, 'Question bank '.$suffix);
        $g = $this->seedExamGradeGraph($school, suffix: $suffix);
        $branch = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
            'school_id' => $school, 'code' => 'BR-'.substr(uniqid(), -6), 'name' => 'فرع '.$suffix, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $other = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'OTH'.substr(uniqid(), -4), 'name' => 'مادة أخرى', 'subject_type' => 1, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['school' => $school, 'year' => $g['year_id'], 'subject' => $g['subject_id'], 'other_subject' => $other, 'branch' => $branch];
    }

    /** A grades_teacher user linked to a teacher with an active assignment for the scene's subject. */
    private function teacher(array $s, bool $assigned = true): User
    {
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantGradesTeacher($user, $s['school']);
        $teacherId = (int) DB::table(SchemaHelper::qualified('teachers', 'teachers'))->insertGetId([
            'user_id' => $user->id, 'employee_code' => 'EMP-'.uniqid(), 'first_name' => 'معلم', 'last_name' => 'البنك',
            'full_name' => 'معلم البنك', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))->insert([
            'teacher_id' => $teacherId, 'school_id' => $s['school'], 'academic_year_id' => $s['year'], 'is_primary' => true, 'left_at' => null,
        ]);
        if ($assigned) {
            DB::table(SchemaHelper::qualified('teachers', 'teaching_assignments'))->insert([
                'teacher_id' => $teacherId, 'school_id' => $s['school'], 'academic_year_id' => $s['year'], 'subject_id' => $s['subject'],
                'branch_id' => $s['branch'], 'status' => 1, 'effective_from' => now()->subMonth()->toDateString(), 'effective_to' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $user;
    }

    private function manager(array $s): User
    {
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantGradesManager($user, $s['school']);

        return $user;
    }

    private function actAs(User $user, array $s): void
    {
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $s['school'], 'current_academic_year_id' => $s['year']]);
    }

    /** @param  array<string, mixed>  $body */
    private function send(string $url, array $body, string $key): TestResponse
    {
        return $this->from('/bank/questions')->post($url, $body, ['X-Idempotency-Key' => $key]);
    }

    /** @return array<string, mixed> */
    private function single(int $subject, string $stem = 'ما ناتج 2 + 2؟'): array
    {
        return ['subject_id' => $subject, 'type' => 'single_choice', 'stem' => $stem, 'max_mark' => '2', 'options' => ['3', '4', '5'], 'correct' => [1]];
    }

    private function questionId(): int
    {
        return (int) DB::table(SchemaHelper::qualified('bank', 'questions'))->orderByDesc('id')->value('id');
    }

    /** @return list<object> */
    private function versions(int $question): array
    {
        return DB::table(SchemaHelper::qualified('bank', 'question_versions'))->where('question_id', $question)->orderBy('version_no')->get()->all();
    }

    #[Test]
    public function a_teacher_writes_and_submits_and_another_person_approves_then_a_revision_supersedes_it(): void
    {
        $s = $this->scene();
        $teacher = $this->teacher($s);
        $this->actAs($teacher, $s);

        $this->send('/bank/questions', $this->single($s['subject']), 'bank-create-1')->assertSessionHas('success', 'flash.bank.created');
        $q = $this->questionId();
        [$v1] = $this->versions($q);
        $this->assertSame(['draft', 1, (int) $teacher->id], [$v1->status, (int) $v1->version_no, (int) $v1->created_by]);
        // jsonb keeps its own key order; the content is what matters.
        $this->assertEquals(['options' => [['key' => 'A', 'text' => '3'], ['key' => 'B', 'text' => '4'], ['key' => 'C', 'text' => '5']], 'correct' => ['B']], json_decode($v1->payload, true));

        $edited = ['type' => 'true_false', 'stem' => '2 + 2 = 4', 'max_mark' => '1', 'answer' => true];
        $this->send("/bank/questions/versions/{$v1->id}/update", $edited, 'bank-upd-1')->assertSessionHas('success', 'flash.bank.saved');
        $this->send("/bank/questions/versions/{$v1->id}/submit", [], 'bank-sub-1')->assertSessionHas('success', 'flash.bank.submitted');
        $this->send("/bank/questions/versions/{$v1->id}/update", $edited + ['stem' => 'بعد الإرسال'], 'bank-upd-2')->assertSessionHas('error', 'bank.invalid_state');
        $this->assertSame(['in_review', '2 + 2 = 4', 'true_false'], [$this->versions($q)[0]->status, $this->versions($q)[0]->stem, $this->versions($q)[0]->type]);

        $manager = $this->manager($s);
        $this->actAs($manager, $s);
        $this->send("/bank/questions/versions/{$v1->id}/decide", ['decision' => 'approve'], 'bank-dec-1')->assertSessionHas('success', 'flash.bank.approved');
        $this->assertSame(['approved', (int) $manager->id], [$this->versions($q)[0]->status, (int) $this->versions($q)[0]->decided_by]);

        $this->actAs($teacher, $s);
        $this->send("/bank/questions/{$q}/revise", [], 'bank-rev-1')->assertSessionHas('success', 'flash.bank.revised');
        $this->send("/bank/questions/{$q}/revise", [], 'bank-rev-2')->assertSessionHas('error', 'bank.open_version_exists');
        $v2 = $this->versions($q)[1];
        $this->assertSame(['draft', 2, '2 + 2 = 4'], [$v2->status, (int) $v2->version_no, $v2->stem]);
        $this->send("/bank/questions/versions/{$v2->id}/submit", [], 'bank-sub-2')->assertSessionHas('success');

        $this->actAs($manager, $s);
        $this->send("/bank/questions/versions/{$v2->id}/decide", ['decision' => 'approve', 'note' => 'تمت المراجعة'], 'bank-dec-2')->assertSessionHas('success');
        $this->assertSame(['superseded', 'approved'], array_map(static fn (object $v): string => $v->status, $this->versions($q)));
    }

    #[Test]
    public function the_author_never_decides_their_own_version_and_a_rejection_needs_a_note(): void
    {
        $s = $this->scene();
        $manager = $this->manager($s);
        $this->actAs($manager, $s);

        $this->send('/bank/questions', $this->single($s['subject']), 'bank-4e-create')->assertSessionHas('success');
        $q = $this->questionId();
        $v = $this->versions($q)[0];
        $this->send("/bank/questions/versions/{$v->id}/submit", [], 'bank-4e-sub')->assertSessionHas('success');
        $this->send("/bank/questions/versions/{$v->id}/decide", ['decision' => 'approve'], 'bank-4e-self')->assertSessionHas('error', 'bank.four_eyes');
        $this->assertSame('in_review', $this->versions($q)[0]->status);

        $this->actAs($this->manager($s), $s);
        $this->send("/bank/questions/versions/{$v->id}/decide", ['decision' => 'reject'], 'bank-4e-nonote')->assertSessionHasErrors('note');
        $this->send("/bank/questions/versions/{$v->id}/decide", ['decision' => 'reject', 'note' => 'الخيارات غير واضحة'], 'bank-4e-rej')->assertSessionHas('success', 'flash.bank.rejected');
        $this->assertSame('rejected', $this->versions($q)[0]->status);
        $this->send("/bank/questions/versions/{$v->id}/decide", ['decision' => 'approve'], 'bank-4e-again')->assertSessionHas('error', 'bank.invalid_state');
    }

    #[Test]
    public function a_teacher_is_limited_to_their_subjects_and_cannot_approve_or_retire(): void
    {
        $s = $this->scene();
        $teacher = $this->teacher($s);
        $this->actAs($teacher, $s);

        $this->send('/bank/questions', $this->single($s['other_subject']), 'bank-scope-other')->assertSessionHas('error', 'bank.subject_not_allowed');
        $this->assertSame(0, DB::table(SchemaHelper::qualified('bank', 'questions'))->count());

        $this->actAs($this->manager($s), $s);
        $this->send('/bank/questions', $this->single($s['other_subject']), 'bank-scope-mgr')->assertSessionHas('success');
        $q = $this->questionId();
        $v = $this->versions($q)[0];

        $this->actAs($teacher, $s);
        $this->get("/bank/questions/{$q}")->assertNotFound();
        $this->get('/bank/questions')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('bank/questions/index')
            ->where('questions.total', 0)
            ->where('abilities.approve', false));
        $this->send("/bank/questions/versions/{$v->id}/update", ['type' => 'long_answer', 'stem' => 'x', 'max_mark' => 1], 'bank-scope-upd')->assertSessionHas('error', 'bank.not_author');
        $this->send("/bank/questions/versions/{$v->id}/decide", ['decision' => 'approve'], 'bank-scope-dec')->assertForbidden();
        $this->send("/bank/questions/{$q}/retire", ['reason' => 'قديم'], 'bank-scope-ret')->assertForbidden();

        $unassigned = $this->teacher($s, assigned: false);
        $this->actAs($unassigned, $s);
        $this->send('/bank/questions', $this->single($s['subject']), 'bank-scope-none')->assertSessionHas('error', 'bank.subject_not_allowed');
    }

    /** Replaces the user's roles with a test role holding only the bank view permission (no author, no approve). */
    private function viewOnly(User $user, int $school): void
    {
        $roles = SchemaHelper::qualified('security', 'roles');
        DB::table($roles)->updateOrInsert(['code' => 'bank_view_only_test'], ['name' => 'Bank view only (test)', 'description' => null, 'is_system' => false]);
        $roleId = (int) DB::table($roles)->where('code', 'bank_view_only_test')->value('id');
        foreach (['bank.question.view', 'exam.view'] as $code) {
            $permissionId = (int) DB::table(SchemaHelper::qualified('security', 'permissions'))->where('code', $code)->value('id');
            DB::table(SchemaHelper::qualified('security', 'role_permissions'))->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId], []);
        }
        DB::table(SchemaHelper::qualified('security', 'user_roles'))->where('user_id', $user->id)->delete();
        app(SecurityPermissionSeeder::class)->assignRole($user, 'bank_view_only_test', $school);
    }

    #[Test]
    public function the_answer_key_is_shown_to_authors_and_approvers_only(): void
    {
        $s = $this->scene();
        $this->actAs($this->manager($s), $s);
        $this->send('/bank/questions', $this->single($s['subject']), 'bank-key-create')->assertSessionHas('success');
        $q = $this->questionId();

        $this->get("/bank/questions/{$q}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('bank/questions/show')
            ->where('question.versions.0.payload.correct', ['B'])
            ->where('abilities.view_answer_key', true));

        // A view-only teacher of the subject sees the question and its options, never the key.
        $viewer = $this->teacher($s);
        $this->viewOnly($viewer, $s['school']);
        $this->actAs($viewer, $s);
        $this->get("/bank/questions/{$q}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('abilities.view_answer_key', false)
            ->where('abilities.author', false)
            ->where('question.versions.0.payload', ['options' => [['key' => 'A', 'text' => '3'], ['key' => 'B', 'text' => '4'], ['key' => 'C', 'text' => '5']]]));
        $this->send('/bank/questions', $this->single($s['subject']), 'bank-key-viewer')->assertForbidden();

        // A view-only user who teaches nothing sees no question.
        $outsider = User::factory()->create();
        $this->viewOnly($outsider, $s['school']);
        $this->actAs($outsider, $s);
        $this->get("/bank/questions/{$q}")->assertNotFound();
    }

    #[Test]
    public function a_repeated_key_replays_and_the_same_key_with_another_request_is_refused(): void
    {
        $s = $this->scene();
        $this->actAs($this->manager($s), $s);

        $this->send('/bank/questions', $this->single($s['subject']), 'bank-idem-1')->assertSessionHas('success');
        $this->send('/bank/questions', $this->single($s['subject']), 'bank-idem-1')->assertSessionHas('success');
        $this->assertSame(1, DB::table(SchemaHelper::qualified('bank', 'questions'))->count());
        $this->send('/bank/questions', $this->single($s['subject'], 'سؤال آخر'), 'bank-idem-1')->assertSessionHas('error', 'bank.idempotency_conflict');
        $this->assertSame(1, DB::table(SchemaHelper::qualified('bank', 'questions'))->count());
        $this->from('/bank/questions')->post('/bank/questions', $this->single($s['subject']))->assertSessionHasErrors('X-Idempotency-Key');
    }

    #[Test]
    public function invalid_content_and_forbidden_fields_are_refused(): void
    {
        $s = $this->scene();
        $this->actAs($this->manager($s), $s);

        $this->send('/bank/questions', ['correct' => [0, 1]] + $this->single($s['subject']), 'bank-inv-two')->assertSessionHas('error', 'bank.content_invalid');
        $this->send('/bank/questions', ['type' => 'essay'] + $this->single($s['subject']), 'bank-inv-type')->assertSessionHasErrors('type');
        $this->send('/bank/questions', ['max_mark' => '0'] + $this->single($s['subject']), 'bank-inv-mark')->assertSessionHasErrors('max_mark');
        $this->send('/bank/questions', ['school_id' => 99] + $this->single($s['subject']), 'bank-inv-school')->assertSessionHasErrors('school_id');
        $this->send('/bank/questions', ['status' => 'approved'] + $this->single($s['subject']), 'bank-inv-status')->assertSessionHasErrors('status');
        $this->assertSame(0, DB::table(SchemaHelper::qualified('bank', 'questions'))->count());
    }

    #[Test]
    public function a_retired_question_is_final_and_keeps_its_history(): void
    {
        $s = $this->scene();
        $this->actAs($this->manager($s), $s);
        $this->send('/bank/questions', $this->single($s['subject']), 'bank-ret-create')->assertSessionHas('success');
        $q = $this->questionId();

        $this->send("/bank/questions/{$q}/retire", ['reason' => ' '], 'bank-ret-empty')->assertSessionHasErrors('reason');
        $this->send("/bank/questions/{$q}/retire", ['reason' => 'منهج قديم'], 'bank-ret-1')->assertSessionHas('success', 'flash.bank.retired');
        $row = DB::table(SchemaHelper::qualified('bank', 'questions'))->where('id', $q)->first();
        $this->assertSame(['retired', 'منهج قديم'], [$row->status, $row->retire_reason]);
        $this->assertCount(1, $this->versions($q));

        $this->send("/bank/questions/{$q}/retire", ['reason' => 'مرة ثانية'], 'bank-ret-2')->assertSessionHas('error', 'bank.question_retired');
        $this->send("/bank/questions/{$q}/revise", [], 'bank-ret-rev')->assertSessionHas('error', 'bank.question_retired');
        $this->get('/bank/questions?status=retired')->assertOk()->assertInertia(fn (Assert $page) => $page->where('questions.total', 1));
    }

    #[Test]
    public function another_schools_question_is_not_found_and_ids_are_numeric(): void
    {
        $a = $this->scene('BANKA');
        $this->actAs($this->manager($a), $a);
        $this->send('/bank/questions', $this->single($a['subject']), 'bank-x-create')->assertSessionHas('success');
        $q = $this->questionId();
        $v = $this->versions($q)[0];

        $b = $this->scene('BANKB');
        $this->actAs($this->manager($b), $b);
        $this->get("/bank/questions/{$q}")->assertNotFound();
        $this->send("/bank/questions/versions/{$v->id}/submit", [], 'bank-x-sub')->assertNotFound();
        $this->send("/bank/questions/{$q}/retire", ['reason' => 'سبب'], 'bank-x-ret')->assertNotFound();
        $this->get('/bank/questions')->assertOk()->assertInertia(fn (Assert $page) => $page->where('questions.total', 0));
        $this->get('/bank/questions/abc')->assertNotFound();
        $this->assertSame('draft', $this->versions($q)[0]->status);
    }

    #[Test]
    public function the_list_filters_and_searches_on_the_server(): void
    {
        $s = $this->scene();
        $this->actAs($this->manager($s), $s);
        $this->send('/bank/questions', $this->single($s['subject'], 'عاصمة اليمن'), 'bank-list-1')->assertSessionHas('success');
        $this->send('/bank/questions', $this->single($s['other_subject'], 'أكبر محيط'), 'bank-list-2')->assertSessionHas('success');

        // Subjects are global (no school_id): an approver is offered every active subject, these two among them.
        $this->get('/bank/questions')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('bank/questions/index')->where('questions.total', 2)->where('abilities.approve', true)
            ->where('subjects', fn ($subjects): bool => collect($subjects)->pluck('id')->intersect([$s['subject'], $s['other_subject']])->count() === 2));
        $this->get("/bank/questions?subject_id={$s['other_subject']}")->assertInertia(fn (Assert $page) => $page
            ->where('questions.total', 1)->where('questions.data.0.stem_excerpt', 'أكبر محيط'));
        $this->get('/bank/questions?search=اليمن')->assertInertia(fn (Assert $page) => $page->where('questions.total', 1));
        $this->get('/bank/questions?search=%25')->assertInertia(fn (Assert $page) => $page->where('questions.total', 0));
        $this->get('/bank/questions?status=in_review')->assertInertia(fn (Assert $page) => $page->where('questions.total', 0));
        $this->get('/bank/questions?status=draft')->assertInertia(fn (Assert $page) => $page->where('questions.total', 2));
    }

    #[Test]
    public function instants_are_sent_as_utc_iso_8601_with_the_display_time_zone(): void
    {
        $s = $this->scene();
        $this->actAs($this->manager($s), $s);
        $this->send('/bank/questions', $this->single($s['subject']), 'bank-tz-create')->assertSessionHas('success');
        $q = $this->questionId();
        $v = $this->versions($q)[0];
        $this->send("/bank/questions/versions/{$v->id}/submit", [], 'bank-tz-sub')->assertSessionHas('success');
        // A known instant: 13:16:44 UTC is 16:16:44 in Asia/Baghdad (UTC+3, no daylight saving).
        DB::table(SchemaHelper::qualified('bank', 'question_versions'))->where('id', $v->id)->update(['submitted_at' => '2026-10-27 13:16:44+00']);

        $this->get("/bank/questions/{$q}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('timezone', 'Asia/Baghdad')
            ->where('question.versions.0.submitted_at', '2026-10-27T13:16:44+00:00')
            ->where('question.versions.0.created_at', fn (string $at): bool => str_ends_with($at, '+00:00')));
        $this->assertSame('2026-10-27 16:16', (new \DateTimeImmutable('2026-10-27T13:16:44+00:00'))->setTimezone(new \DateTimeZone('Asia/Baghdad'))->format('Y-m-d H:i'));
        $this->assertSame('2026-10-27 13:16:44+00', (string) DB::table(SchemaHelper::qualified('bank', 'question_versions'))->where('id', $v->id)->value('submitted_at'), 'storage is unchanged');

        config(['attendance.default_timezone' => 'Not/AZone']);
        $this->get("/bank/questions/{$q}")->assertInertia(fn (Assert $page) => $page->where('timezone', 'UTC'));
    }

    #[Test]
    public function a_user_without_bank_permissions_is_forbidden(): void
    {
        $s = $this->scene();
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantGradesViewer($user, $s['school']);
        $this->actAs($user, $s);

        $this->get('/bank/questions')->assertForbidden();
        $this->send('/bank/questions', $this->single($s['subject']), 'bank-none')->assertForbidden();
    }
}
