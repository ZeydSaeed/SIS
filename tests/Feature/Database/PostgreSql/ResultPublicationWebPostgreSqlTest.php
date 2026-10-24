<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Results\SeedsResultSheetScene;

/**
 * EXAMIS A1 — publication of official annual results / year GPAs: separate permission from finalize, refused unless
 * every student of the scope has a complete official result, one active publication per scope, withdrawal with a
 * reason, and result rows are never touched.
 */
final class ResultPublicationWebPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsResultSheetScene;

    private function table(): string
    {
        return SchemaHelper::qualified('results', 'result_publications');
    }

    /** @param  array<string, mixed>  $s */
    private function post_(string $path, array $s, array $body = [], string $key = 'pub'): TestResponse
    {
        return $this->from('/results/year-sheet?academic_year_id='.$s['year_id'])->post(
            $path,
            $body + ['academic_year_id' => $s['year_id'], 'class_id' => $s['class'], 'target' => 'annual'],
            ['X-Idempotency-Key' => $key],
        );
    }

    /** Finalize the annual result for the one student with a finalized grade; the other two stay without one. */
    private function sceneWithOneOfficialAnnual(): array
    {
        $s = $this->scene();
        $this->as('ResultsManager', $s['school']);
        $term = ['academic_year_id' => $s['year_id'], 'term_id' => $s['term_id'], 'subject_id' => $s['subject_id'], 'class_id' => $s['class']];
        $this->post('/results/sheet/calculate', $term, ['X-Idempotency-Key' => 'pub-t1']);
        $this->post('/results/sheet/finalize', $term, ['X-Idempotency-Key' => 'pub-t2']);
        $this->post_('/results/year-sheet/finalize', $s, [], 'pub-a1');

        return $s;
    }

    #[Test]
    public function publishing_is_refused_while_any_student_lacks_a_complete_official_result(): void
    {
        $s = $this->sceneWithOneOfficialAnnual();
        $this->as('ResultsPublisher', $s['school']);

        $this->post_('/results/year-sheet/publish', $s)->assertSessionHas('error', 'results.publish.not_ready');
        $this->assertSame(0, DB::table($this->table())->count());
    }

    #[Test]
    public function publishing_a_section_with_complete_results_records_a_snapshot_once(): void
    {
        $s = $this->sceneWithOneOfficialAnnual();
        // The two students without an official annual result move to another section, so the scene section is complete.
        $elsewhere = (int) $this->createSectionForClass($s['class'])->id;
        DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->whereIn('id', [$s['entered'], $s['ungraded']])->update(['section_id' => $elsewhere]);
        $annualBefore = DB::table(SchemaHelper::qualified('results', 'annual_results'))->where('school_id', $s['school'])->orderBy('id')->pluck('id')->all();
        $this->as('ResultsPublisher', $s['school']);
        $section = $s['section'];

        $this->post_('/results/year-sheet/publish', $s, ['section_id' => $elsewhere])->assertSessionHas('error', 'results.publish.not_ready');
        $this->post_('/results/year-sheet/publish', $s, ['section_id' => $section])->assertSessionHas('success', 'flash.results.year.annual.publish');
        $row = DB::table($this->table())->first();
        $this->assertSame('published', $row->status);
        $this->assertSame(64, strlen($row->source_fingerprint));
        $this->assertSame($annualBefore, DB::table(SchemaHelper::qualified('results', 'annual_results'))->where('school_id', $s['school'])->orderBy('id')->pluck('id')->all(), 'result rows are untouched');
        $this->post_('/results/year-sheet/publish', $s, ['section_id' => $section])->assertSessionHas('error', 'results.publish.already_published');
        $this->post_('/results/year-sheet/publish', $s)->assertSessionHas('error', 'results.publish.not_ready');
        $this->assertSame(1, DB::table($this->table())->count());
    }

    /** A section holding only the finalized student, so a publication scope can be complete. */
    private function isolateFinalStudent(array $s): int
    {
        $elsewhere = (int) $this->createSectionForClass($s['class'])->id;
        DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->whereIn('id', [$s['entered'], $s['ungraded']])->update(['section_id' => $elsewhere]);

        return $elsewhere;
    }

    /** @param  array<string, mixed>  $s */
    private function termPublish(array $s, array $overrides = [], string $key = 'tp'): TestResponse
    {
        return $this->from('/results/sheet')->post('/results/sheet/publish', $overrides + [
            'academic_year_id' => $s['year_id'], 'term_id' => $s['term_id'], 'subject_id' => $s['subject_id'], 'class_id' => $s['class'],
        ], ['X-Idempotency-Key' => $key]);
    }

    #[Test]
    public function term_results_publish_per_term_and_subject_and_the_sheet_reports_drift(): void
    {
        $s = $this->sceneWithOneOfficialAnnual();
        $elsewhere = $this->isolateFinalStudent($s);
        $this->as('ResultsPublisher', $s['school']);

        $this->termPublish($s)->assertSessionHas('error', 'results.publish.not_ready');
        $this->termPublish($s, ['section_id' => $elsewhere], 'tp2')->assertSessionHas('error', 'results.publish.not_ready');
        $this->termPublish($s, ['section_id' => $s['section'], 'term_id' => 999999], 'tp3')->assertSessionHas('error', 'results.publish.scope_invalid');
        $this->termPublish($s, ['section_id' => $s['section']], 'tp4')->assertSessionHas('success', 'flash.results.batch.publish');
        $this->termPublish($s, ['section_id' => $s['section']], 'tp5')->assertSessionHas('error', 'results.publish.already_published');

        $row = DB::table($this->table())->first();
        $this->assertSame('term', $row->kind);
        $this->assertSame($s['term_id'], (int) $row->term_id);
        $url = "/results/sheet?academic_year_id={$s['year_id']}&term_id={$s['term_id']}&subject_id={$s['subject_id']}&class_id={$s['class']}";
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('publications.0.drifted', 0)->where('abilities.publish', true));

        DB::table(SchemaHelper::qualified('results', 'term_results'))->where('enrollment_id', $s['final'])->where('is_current_official', true)->update(['result_version' => 3]);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('publications.0.drifted', 1));
    }

    #[Test]
    public function transcripts_publish_only_when_every_student_has_an_up_to_date_one(): void
    {
        $s = $this->sceneWithOneOfficialAnnual();
        $elsewhere = $this->isolateFinalStudent($s);
        $this->as('ResultsManager', $s['school']);
        $this->post_('/results/year-sheet/finalize', $s, ['target' => 'gpa'], 'tr-gpa');
        $this->from('/results/year-sheet')->post('/results/year-sheet/transcripts', ['academic_year_id' => $s['year_id'], 'class_id' => $s['class']], ['X-Idempotency-Key' => 'tr-issue']);
        $this->as('ResultsPublisher', $s['school']);

        $this->post_('/results/year-sheet/publish', $s, ['target' => 'transcript', 'section_id' => $elsewhere], 'tr-p1')->assertSessionHas('error', 'results.publish.not_ready');
        $this->post_('/results/year-sheet/publish', $s, ['target' => 'transcript', 'section_id' => $s['section']], 'tr-p2')->assertSessionHas('success', 'flash.results.year.transcript.publish');
        $this->assertSame('transcript', DB::table($this->table())->value('kind'));
    }

    #[Test]
    public function both_sheets_carry_the_server_side_reconciliation_of_their_scope(): void
    {
        $s = $this->sceneWithOneOfficialAnnual();
        $this->as('ResultsViewer', $s['school']);
        $counts = static fn (mixed $issues): array => array_column(json_decode((string) json_encode($issues), true), 'count', 'code');

        $this->get("/results/year-sheet?academic_year_id={$s['year_id']}&class_id={$s['class']}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('reconciliation.students', 3)->where('reconciliation.ready.annual', false)
                ->where('reconciliation.issues', fn ($issues): bool => ($counts($issues)['annual_missing'] ?? 0) === 2
                    && ($counts($issues)['gpa_missing'] ?? 0) === 3 && ($counts($issues)['transcript_missing'] ?? 0) === 3));

        $this->get("/results/sheet?academic_year_id={$s['year_id']}&term_id={$s['term_id']}&subject_id={$s['subject_id']}&class_id={$s['class']}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('reconciliation.ready.term', false)
                ->where('reconciliation.issues', fn ($issues): bool => ($counts($issues)['not_graded'] ?? 0) === 1 && ($counts($issues)['not_finalized'] ?? 0) === 1));
    }

    #[Test]
    public function only_the_publish_permission_publishes_and_finalize_does_not_imply_it(): void
    {
        $s = $this->sceneWithOneOfficialAnnual();

        $this->as('ResultsManager', $s['school']);
        $this->get('/results/year-sheet?academic_year_id='.$s['year_id'])->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('abilities.publish', false));
        $this->post_('/results/year-sheet/publish', $s)->assertForbidden();

        $this->as('ResultsViewer', $s['school']);
        $this->post_('/results/year-sheet/publish', $s)->assertForbidden();
        $this->assertSame(0, DB::table($this->table())->count());
    }

    #[Test]
    public function the_database_refuses_deletes_edits_of_the_snapshot_and_a_second_active_row(): void
    {
        $s = $this->sceneWithOneOfficialAnnual();
        $id = $this->insertPublication($s);

        $this->refuses(fn () => DB::table($this->table())->where('id', $id)->delete(), 'never deleted');
        $this->refuses(fn () => DB::table($this->table())->where('id', $id)->update(['participant_count' => 9]), 'immutable');
        $this->refuses(fn () => $this->insertPublication($s), 'result_publications_active_scope_unique');
        $this->refuses(fn () => DB::table($this->table())->where('id', $id)->update(['status' => 'withdrawn']), 'result_publications_withdrawn_check');
    }

    #[Test]
    public function withdrawal_keeps_the_row_needs_a_reason_and_allows_a_new_publication(): void
    {
        $s = $this->sceneWithOneOfficialAnnual();
        $id = $this->insertPublication($s);
        $this->as('ResultsPublisher', $s['school']);
        $url = "/results/year-sheet/publications/{$id}/withdraw";

        $this->from('/results/year-sheet')->post($url, ['reason' => ''], ['X-Idempotency-Key' => 'w0'])->assertSessionHasErrors('reason');
        $this->from('/results/year-sheet')->post($url, ['reason' => 'خطأ في إدخال الدرجات'], ['X-Idempotency-Key' => 'w1'])->assertSessionHas('success', 'flash.results.year.withdraw');
        $this->from('/results/year-sheet')->post($url, ['reason' => 'خطأ في إدخال الدرجات'], ['X-Idempotency-Key' => 'w1'])->assertSessionHas('success', 'flash.results.year.withdraw');
        $row = DB::table($this->table())->where('id', $id)->first();
        $this->assertSame('withdrawn', $row->status);
        $this->assertSame('خطأ في إدخال الدرجات', $row->withdraw_reason);
        $this->assertNotNull($row->withdrawn_at);

        $this->from('/results/year-sheet')->post($url, ['reason' => 'مرة ثانية'], ['X-Idempotency-Key' => 'w2'])->assertNotFound();
        $this->insertPublication($s);
        $this->assertSame(2, DB::table($this->table())->count());
    }

    #[Test]
    public function another_school_cannot_withdraw_a_publication(): void
    {
        $s = $this->sceneWithOneOfficialAnnual();
        $id = $this->insertPublication($s);
        $other = $this->createSchool('SCH-OTHER-PUB', 'Other school');
        $this->as('ResultsPublisher', $other);

        $this->from('/results/year-sheet')->post("/results/year-sheet/publications/{$id}/withdraw", ['reason' => 'محاولة'], ['X-Idempotency-Key' => 'w3'])
            ->assertNotFound();
        $this->assertSame('published', DB::table($this->table())->where('id', $id)->value('status'));
    }

    #[Test]
    public function the_sheet_reports_a_publication_whose_official_result_changed_after_publishing(): void
    {
        $s = $this->sceneWithOneOfficialAnnual();
        $this->as('ResultsPublisher', $s['school']);
        $elsewhere = (int) $this->createSectionForClass($s['class'])->id;
        DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->whereIn('id', [$s['entered'], $s['ungraded']])->update(['section_id' => $elsewhere]);
        $this->post_('/results/year-sheet/publish', $s, ['section_id' => $s['section']])->assertSessionHas('success');
        $url = "/results/year-sheet?academic_year_id={$s['year_id']}&class_id={$s['class']}";

        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('publications.0.drifted', 0));
        DB::table(SchemaHelper::qualified('results', 'annual_results'))->where('enrollment_id', $s['final'])->where('is_current_official', true)->update(['result_version' => 2]);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('publications.0.drifted', 1));
    }

    /** @param  array<string, mixed>  $s */
    private function insertPublication(array $s): int
    {
        return (int) DB::table($this->table())->insertGetId([
            'school_id' => $s['school'], 'academic_year_id' => $s['year_id'], 'class_id' => $s['class'], 'section_id' => null,
            'kind' => 'annual', 'participant_count' => 1, 'source_fingerprint' => str_repeat('a', 64), 'published_snapshot' => '[]',
        ]);
    }

    private function refuses(callable $action, string $message): void
    {
        try {
            DB::transaction($action);
            $this->fail("expected the database to refuse ({$message})");
        } catch (QueryException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }
}
