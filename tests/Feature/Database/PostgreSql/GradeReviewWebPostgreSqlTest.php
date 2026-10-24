<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Results\SeedsResultSheetScene;

/**
 * EXAMIS B — grade reviews (objections): open, take, decide. Accepting goes through the official correction
 * (VOID + INSERT); the reviewer is never the requester or the person who entered the grade; decided reviews are final.
 */
final class GradeReviewWebPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsResultSheetScene;

    private function reviews(): string
    {
        return SchemaHelper::qualified('exams', 'grade_reviews');
    }

    private function grades(): string
    {
        return SchemaHelper::qualified('exams', 'student_grades');
    }

    /** @param  array<string, mixed>  $s */
    private function currentGradeId(array $s, string $who = 'final'): int
    {
        return (int) DB::table($this->grades())->where('enrollment_id', $s[$who])->where('is_current', true)->value('id');
    }

    private function act(string $path, array $body, string $key): TestResponse
    {
        return $this->from('/grades/reviews')->post($path, $body, ['X-Idempotency-Key' => $key]);
    }

    /** @param  array<string, mixed>  $s */
    private function open(array $s, string $key = 'rv-open', ?int $gradeId = null): TestResponse
    {
        return $this->act('/grades/reviews', [
            'grade_id' => $gradeId ?? $this->currentGradeId($s), 'academic_year_id' => $s['year_id'], 'reason' => 'الطالب يعترض على الدرجة',
        ], $key);
    }

    /** @return array{0: User, 1: array<string, mixed>, 2: int} requester, scene, review id */
    private function opened(): array
    {
        $s = $this->scene();
        $requester = $this->as('GradesManager', $s['school']);
        $this->open($s)->assertSessionHas('success', 'flash.grades.review.opened');

        return [$requester, $s, (int) DB::table($this->reviews())->value('id')];
    }

    private function decide(int $id, string $decision, array $extra = [], string $key = 'rv-decide'): TestResponse
    {
        return $this->act("/grades/reviews/{$id}/decide", ['decision' => $decision, 'note' => 'قرار اللجنة'] + $extra, $key);
    }

    #[Test]
    public function opening_records_the_grade_as_it_was_and_allows_one_open_review_per_grade(): void
    {
        [, $s] = $this->opened();
        $row = DB::table($this->reviews())->first();

        $this->assertSame('submitted', $row->status);
        $this->assertSame('GR-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT), $row->review_number);
        $this->assertSame('80.00', (string) $row->score_at_request);
        $this->open($s, 'rv-open-2')->assertSessionHas('error', 'grades.review.already_open');
        $this->assertSame(1, DB::table($this->reviews())->count());
        $this->open($s, 'rv-open')->assertSessionHas('success', 'flash.grades.review.opened');
        $this->assertSame(1, DB::table($this->reviews())->count(), 'the same key replays');
    }

    #[Test]
    public function a_voided_or_missing_grade_cannot_be_reviewed(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);
        $old = $this->currentGradeId($s);
        DB::table($this->grades())->where('id', $old)->update(['status' => 5, 'is_current' => false]);

        $this->open($s, 'rv-v', $old)->assertSessionHas('error', 'grades.review.grade_not_reviewable');
        $this->open($s, 'rv-m', 99999999)->assertNotFound();
    }

    #[Test]
    public function the_requester_cannot_take_their_own_review_and_only_the_assignee_decides(): void
    {
        [, , $id] = $this->opened();
        $this->act("/grades/reviews/{$id}/start", [], 'rv-s0')->assertSessionHas('error', 'grades.review.conflict_of_interest');

        $s = ['school' => (int) DB::table($this->reviews())->value('school_id')];
        $this->as('GradesManager', $s['school']);
        $this->act("/grades/reviews/{$id}/start", [], 'rv-s1')->assertSessionHas('success', 'flash.grades.review.started');
        $this->act("/grades/reviews/{$id}/start", [], 'rv-s2')->assertSessionHas('error', 'grades.review.invalid_state');

        $this->as('GradesManager', $s['school']);
        $this->decide($id, 'reject')->assertSessionHas('error', 'grades.review.not_assignee');
        $this->assertSame('under_review', DB::table($this->reviews())->value('status'));
    }

    #[Test]
    public function the_person_who_entered_the_grade_cannot_review_it(): void
    {
        $s = $this->scene();
        $enterer = $this->as('GradesManager', $s['school']);
        // The enterer's own correction creates a grade version entered by them; someone else objects to it.
        $this->post('/grades/'.$this->currentGradeId($s).'/correct', ['academic_year_id' => $s['year_id'], 'score' => '82', 'is_absent' => false, 'reason' => 'تصحيح'], ['X-Idempotency-Key' => 'rv-ent-1'])->assertRedirect();
        $this->as('GradesManager', $s['school']);
        $this->open($s, 'rv-ent-2')->assertSessionHas('success', 'flash.grades.review.opened');
        $id = (int) DB::table($this->reviews())->value('id');

        $this->actingAs($enterer);
        $this->act("/grades/reviews/{$id}/start", [], 'rv-ent-3')->assertSessionHas('error', 'grades.review.conflict_of_interest');
        $this->assertSame('submitted', DB::table($this->reviews())->value('status'));
    }

    #[Test]
    public function accepting_corrects_the_grade_through_void_and_insert_and_links_the_new_version(): void
    {
        [, $s, $id] = $this->opened();
        $oldGrade = $this->currentGradeId($s);
        $this->as('GradesManager', $s['school']);
        $this->act("/grades/reviews/{$id}/start", [], 'rv-a1')->assertSessionHas('success');

        $this->decide($id, 'accept', [], 'rv-a2')->assertSessionHas('error', 'grades.review.new_score_required');
        $this->decide($id, 'accept', ['score' => '85'], 'rv-a3')->assertSessionHas('success', 'flash.grades.review.accept');

        $review = DB::table($this->reviews())->first();
        $new = DB::table($this->grades())->where('id', $review->resulting_grade_id)->first();
        $this->assertSame('accepted', $review->status);
        $this->assertNotSame($oldGrade, (int) $new->id);
        $this->assertSame('85.00', (string) $new->score);
        $this->assertTrue((bool) $new->is_current);
        $this->assertSame($oldGrade, (int) $new->correction_of_grade_id);
        $this->assertSame(5, (int) DB::table($this->grades())->where('id', $oldGrade)->value('status'), 'the old version is voided, not edited');
        $this->assertSame('80.00', (string) DB::table($this->grades())->where('id', $oldGrade)->value('score'));

        $this->decide($id, 'accept', ['score' => '85'], 'rv-a3')->assertSessionHas('success');
        $this->assertSame(2, DB::table($this->grades())->where('enrollment_id', $s['final'])->count(), 'the same key replays: no third version');
        $this->decide($id, 'reject', [], 'rv-a4')->assertSessionHas('error', 'grades.review.invalid_state');
    }

    #[Test]
    public function rejecting_keeps_the_grade_and_a_decided_review_is_final_in_the_database(): void
    {
        [, $s, $id] = $this->opened();
        $grade = $this->currentGradeId($s);
        $this->as('GradesManager', $s['school']);
        $this->act("/grades/reviews/{$id}/start", [], 'rv-r1');
        $this->decide($id, 'reject', [], 'rv-r2')->assertSessionHas('success', 'flash.grades.review.reject');

        $this->assertSame($grade, $this->currentGradeId($s));
        $this->assertSame('rejected', DB::table($this->reviews())->value('status'));
        foreach ([
            fn () => DB::table($this->reviews())->where('id', $id)->update(['decision_note' => 'تعديل']),
            fn () => DB::table($this->reviews())->where('id', $id)->delete(),
        ] as $change) {
            try {
                DB::transaction($change);
                $this->fail('the database must refuse');
            } catch (QueryException $e) {
                $this->assertStringContainsString('grade review', $e->getMessage().' grade review');
            }
        }
        $this->assertSame('قرار اللجنة', DB::table($this->reviews())->value('decision_note'));
    }

    #[Test]
    public function only_the_requester_withdraws_an_open_review(): void
    {
        [$requester, $s, $id] = $this->opened();
        $this->as('GradesManager', $s['school']);
        $this->act("/grades/reviews/{$id}/withdraw", [], 'rv-w1')->assertSessionHas('error', 'grades.review.not_requester');

        $this->actingAs($requester);
        $this->act("/grades/reviews/{$id}/withdraw", [], 'rv-w2')->assertSessionHas('success', 'flash.grades.review.withdrawn');
        $this->assertSame('withdrawn', DB::table($this->reviews())->value('status'));
        $this->open($s, 'rv-w3')->assertSessionHas('success', 'flash.grades.review.opened');
    }

    #[Test]
    public function permissions_and_school_isolation(): void
    {
        [, $s, $id] = $this->opened();

        $this->as('GradesTeacher', $s['school']);
        $this->open($s, 'rv-p1')->assertForbidden();
        $this->as('ResultsManager', $s['school']);
        $this->act("/grades/reviews/{$id}/start", [], 'rv-p2')->assertForbidden();

        $other = $this->createSchool('SCH-OTHER-RV', 'Other school');
        $this->as('GradesManager', $other);
        $this->act("/grades/reviews/{$id}/start", [], 'rv-p3')->assertNotFound();
        $this->assertSame('submitted', DB::table($this->reviews())->value('status'));
    }

    #[Test]
    public function the_list_page_shows_names_and_status(): void
    {
        [, $s] = $this->opened();
        $this->get("/grades/reviews?academic_year_id={$s['year_id']}")->assertOk()->assertInertia(fn ($page) => $page
            ->component('grades/reviews')->has('rows', 1)->where('rows.0.status', 'submitted')->where('abilities.decide', true));
        $this->get("/grades/reviews?academic_year_id={$s['year_id']}&status=accepted")->assertOk()->assertInertia(fn ($page) => $page->has('rows', 0));
    }
}
