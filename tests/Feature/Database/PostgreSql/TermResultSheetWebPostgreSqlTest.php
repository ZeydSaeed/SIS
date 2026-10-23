<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Infrastructure\Persistence\Eloquent\SecurityAuditLogRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Results\SeedsResultSheetScene;

/**
 * EXAMIS U12 — term-results sheet: calculate / finalize the term result of a subject for every seated student of a
 * class through the single-student commands; finalize never re-finalizes an official result.
 */
final class TermResultSheetWebPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsResultSheetScene;

    /** @param  array<string, mixed>  $s  @return array<string, mixed> */
    private function payload(array $s, array $overrides = []): array
    {
        return $overrides + ['academic_year_id' => $s['year_id'], 'term_id' => $s['term_id'], 'subject_id' => $s['subject_id'], 'class_id' => $s['class']];
    }

    /** @param  array<string, mixed>  $s */
    private function submit(array $s, string $mode, string $key, array $overrides = []): TestResponse
    {
        return $this->from('/results/sheet?academic_year_id='.$s['year_id'])->post("/results/sheet/{$mode}", $this->payload($s, $overrides), ['X-Idempotency-Key' => $key]);
    }

    /** @return array<int, string> enrollment → status */
    private function outcomes(TestResponse $response): array
    {
        return array_column($response->getSession()->get('term_results_outcome')['outcomes'] ?? [], 'status', 'enrollment_id');
    }

    private function results(int $enrollment, string $flag): int
    {
        return DB::table(SchemaHelper::qualified('results', 'term_results'))->where('enrollment_id', $enrollment)->where($flag, true)->count();
    }

    #[Test]
    public function calculate_produces_an_operational_result_per_graded_student(): void
    {
        $s = $this->scene();
        $user = $this->as('ResultsManager', $s['school']);

        $response = $this->submit($s, 'calculate', 'trs-calc');

        $this->assertSame([
            $s['final'] => 'calculated',
            $s['entered'] => 'calculated',
            $s['ungraded'] => 'results.term_no_eligible_grades',
        ], $this->outcomes($response));
        $response->assertSessionHas('error', 'flash.results.batch.partial');
        $this->assertSame('80.00', (string) DB::table(SchemaHelper::qualified('results', 'term_results'))->where('enrollment_id', $s['final'])->where('is_current_operational', true)->value('weighted_total'));
        $this->assertSame(0, $this->results($s['final'], 'is_current_official'));
        $this->assertTrue(SecurityAuditLogRecord::query()->where('action', 'results.web.term_batch.calculate')->where('actor_id', $user->id)->exists());
    }

    #[Test]
    public function finalize_needs_finalized_grades_and_never_refinalizes(): void
    {
        $s = $this->scene();
        $this->as('ResultsManager', $s['school']);

        $first = $this->submit($s, 'finalize', 'trs-final-1');
        $this->assertSame([
            $s['final'] => 'finalized',
            $s['entered'] => 'results.official_dataset_incomplete',
            $s['ungraded'] => 'results.official_dataset_incomplete',
        ], $this->outcomes($first));
        $this->assertSame(1, $this->results($s['final'], 'is_current_official'));
        $versions = DB::table(SchemaHelper::qualified('results', 'term_results'))->where('enrollment_id', $s['final'])->count();

        $again = $this->submit($s, 'finalize', 'trs-final-2');
        $this->assertSame('already_official', $this->outcomes($again)[$s['final']]);
        $this->assertSame($versions, DB::table(SchemaHelper::qualified('results', 'term_results'))->where('enrollment_id', $s['final'])->count(), 'no new official version');
    }

    #[Test]
    public function repeating_a_request_replays(): void
    {
        $s = $this->scene();
        $this->as('ResultsManager', $s['school']);
        $this->submit($s, 'calculate', 'trs-repeat');
        $versions = DB::table(SchemaHelper::qualified('results', 'term_results'))->whereIn('enrollment_id', [$s['final'], $s['entered']])->count();

        $again = $this->submit($s, 'calculate', 'trs-repeat');

        $this->assertSame('replayed', $this->outcomes($again)[$s['final']]);
        $this->assertSame($versions, DB::table(SchemaHelper::qualified('results', 'term_results'))->whereIn('enrollment_id', [$s['final'], $s['entered']])->count());
    }

    #[Test]
    public function a_weight_sum_other_than_100_is_reported_per_student(): void
    {
        $s = $this->scene(weight: 40);
        $this->as('ResultsManager', $s['school']);

        $this->assertSame('results.term_weight_sum_invalid', $this->outcomes($this->submit($s, 'calculate', 'trs-weight'))[$s['final']]);
        $this->assertSame(0, $this->results($s['final'], 'is_current_operational'));
    }

    #[Test]
    public function invalid_targets_are_refused_and_process_nobody(): void
    {
        $s = $this->scene();
        $this->as('ResultsManager', $s['school']);
        $otherYear = $this->createAcademicYear('AY-TRS-OTHER');
        $otherTerm = (int) DB::table(SchemaHelper::qualified('academic', 'terms'))->insertGetId([
            'academic_year_id' => $otherYear, 'code' => 'T-TRS-X', 'name' => 'Other term', 'start_date' => '2027-09-01', 'end_date' => '2027-12-31',
            'term_order' => 1, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherClass = (int) $this->createClassForSchool($s['school'], $otherYear)->id;

        $this->submit($s, 'calculate', 'trs-t', ['term_id' => $otherTerm])->assertSessionHas('error', 'results.batch.term_invalid');
        $this->submit($s, 'calculate', 'trs-c', ['class_id' => $otherClass])->assertSessionHas('error', 'results.batch.class_invalid');
        $this->submit($s, 'calculate', 'trs-s', ['section_id' => (int) $this->createSectionForClass($otherClass)->id])->assertSessionHas('error', 'results.batch.section_invalid');
        $this->submit($s, 'calculate', 'trs-e', ['enrollment_ids' => [$s['final']]])->assertSessionHasErrors('enrollment_ids');

        $this->assertSame(0, DB::table(SchemaHelper::qualified('results', 'term_results'))->where('school_id', $s['school'])->count());
    }

    #[Test]
    public function only_results_managers_may_calculate_or_finalize(): void
    {
        $s = $this->scene();
        $this->as('ResultsViewer', $s['school']);
        $this->get('/results/sheet?academic_year_id='.$s['year_id'])->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('abilities.calculate', false)->where('abilities.finalize', false));
        $this->submit($s, 'calculate', 'trs-v1')->assertForbidden();
        $this->submit($s, 'finalize', 'trs-v2')->assertForbidden();

        $this->as('GradesTeacher', $s['school']);
        $this->get('/results/sheet?academic_year_id='.$s['year_id'])->assertForbidden();
        $this->submit($s, 'calculate', 'trs-t1')->assertForbidden();

        $this->assertSame(0, DB::table(SchemaHelper::qualified('results', 'term_results'))->where('school_id', $s['school'])->count());
    }

    #[Test]
    public function the_sheet_lists_the_seated_students_with_their_results(): void
    {
        $s = $this->scene();
        $this->as('ResultsManager', $s['school']);
        $this->submit($s, 'calculate', 'trs-page');

        $this->get("/results/sheet?academic_year_id={$s['year_id']}&term_id={$s['term_id']}&subject_id={$s['subject_id']}&class_id={$s['class']}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('results/sheet')
                ->where('abilities.calculate', true)->where('abilities.finalize', true)
                ->where('subjects', fn ($subjects) => collect($subjects)->pluck('id')->contains($s['subject_id']))
                ->has('rows', 3)
                ->where('rows', fn ($rows) => collect($rows)->firstWhere('enrollment_id', $s['final'])['operational']['weighted_total'] === '80.00'
                    && collect($rows)->firstWhere('enrollment_id', $s['final'])['finalized_grades'] === 1
                    && collect($rows)->firstWhere('enrollment_id', $s['ungraded'])['operational'] === null));
    }
}
