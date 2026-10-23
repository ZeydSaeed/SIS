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
 * EXAMIS U13 — annual / GPA sheet: calculate / finalize the annual result and the year GPA of every active student of a
 * class through the single-student commands; finalize never re-finalizes an official annual result or GPA.
 */
final class YearResultSheetWebPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsResultSheetScene;

    /**
     * Term results through the term sheet: the finalized-grade student gets an operational and an official term result,
     * the entered-grade student an operational one only, the ungraded student none.
     *
     * @return array<string, mixed>
     */
    private function sceneWithTermResults(): array
    {
        $s = $this->scene();
        $this->as('ResultsManager', $s['school']);
        $term = ['academic_year_id' => $s['year_id'], 'term_id' => $s['term_id'], 'subject_id' => $s['subject_id'], 'class_id' => $s['class']];
        $this->post('/results/sheet/calculate', $term, ['X-Idempotency-Key' => 'yrs-term-calc'])->assertRedirect();
        $this->post('/results/sheet/finalize', $term, ['X-Idempotency-Key' => 'yrs-term-final'])->assertRedirect();

        return $s;
    }

    /** @param  array<string, mixed>  $s */
    private function submit(array $s, string $target, string $mode, string $key, array $overrides = []): TestResponse
    {
        return $this->from('/results/year-sheet?academic_year_id='.$s['year_id'])->post(
            "/results/year-sheet/{$mode}",
            $overrides + ['academic_year_id' => $s['year_id'], 'class_id' => $s['class'], 'target' => $target],
            ['X-Idempotency-Key' => $key],
        );
    }

    /** @return array<int, string> enrollment → status */
    private function outcomes(TestResponse $response): array
    {
        return array_column($response->getSession()->get('year_results_outcome')['outcomes'] ?? [], 'status', 'enrollment_id');
    }

    private function rowsOf(string $table, int $enrollment, ?string $flag = null): int
    {
        return DB::table(SchemaHelper::qualified('results', $table))->where('enrollment_id', $enrollment)
            ->when($flag !== null, static fn ($q) => $q->where($flag, true))->count();
    }

    #[Test]
    public function annual_calculate_and_finalize_follow_the_term_results(): void
    {
        $s = $this->sceneWithTermResults();

        $calc = $this->submit($s, 'annual', 'calculate', 'yrs-a-calc');
        $this->assertSame([
            $s['final'] => 'calculated',
            $s['entered'] => 'calculated',
            $s['ungraded'] => 'results.annual_no_term_results',
        ], $this->outcomes($calc));
        $this->assertSame('80.00', (string) DB::table(SchemaHelper::qualified('results', 'annual_results'))->where('enrollment_id', $s['final'])->where('is_current_operational', true)->value('average_weighted_total'));

        $final = $this->submit($s, 'annual', 'finalize', 'yrs-a-final');
        $this->assertSame([
            $s['final'] => 'finalized',
            $s['entered'] => 'results.annual_no_term_results',
            $s['ungraded'] => 'results.annual_no_term_results',
        ], $this->outcomes($final));
        $final->assertSessionHas('error', 'flash.results.batch.partial');
        $this->assertSame(1, $this->rowsOf('annual_results', $s['final'], 'is_current_official'));
        $this->assertSame(0, $this->rowsOf('annual_results', $s['entered'], 'is_current_official'));
        $this->assertTrue(SecurityAuditLogRecord::query()->where('action', 'results.web.year_batch.annual.finalize')->exists());

        $versions = $this->rowsOf('annual_results', $s['final']);
        $this->assertSame('already_official', $this->outcomes($this->submit($s, 'annual', 'finalize', 'yrs-a-final-2'))[$s['final']]);
        $this->assertSame($versions, $this->rowsOf('annual_results', $s['final']), 'no new official version');
    }

    #[Test]
    public function gpa_needs_the_official_annual_result_and_never_refinalizes(): void
    {
        $s = $this->sceneWithTermResults();

        $this->assertSame('results.gpa_official_annual_missing', $this->outcomes($this->submit($s, 'gpa', 'calculate', 'yrs-g-early'))[$s['final']]);
        $this->assertSame(0, $this->rowsOf('gpa_results', $s['final']));

        $this->submit($s, 'annual', 'finalize', 'yrs-g-annual');
        $calc = $this->submit($s, 'gpa', 'calculate', 'yrs-g-calc');
        $this->assertSame('calculated', $this->outcomes($calc)[$s['final']]);
        $this->assertSame('results.gpa_official_annual_missing', $this->outcomes($calc)[$s['entered']]);

        $this->assertSame('finalized', $this->outcomes($this->submit($s, 'gpa', 'finalize', 'yrs-g-final'))[$s['final']]);
        $this->assertSame('80.00', (string) DB::table(SchemaHelper::qualified('results', 'gpa_results'))->where('enrollment_id', $s['final'])->where('is_current_official', true)->value('gpa_value'));

        $versions = $this->rowsOf('gpa_results', $s['final']);
        $this->assertSame('already_official', $this->outcomes($this->submit($s, 'gpa', 'finalize', 'yrs-g-final-2'))[$s['final']]);
        $this->assertSame($versions, $this->rowsOf('gpa_results', $s['final']));
    }

    #[Test]
    public function repeating_a_request_replays(): void
    {
        $s = $this->sceneWithTermResults();
        $this->submit($s, 'annual', 'calculate', 'yrs-repeat');
        $versions = $this->rowsOf('annual_results', $s['final']);

        $this->assertSame('replayed', $this->outcomes($this->submit($s, 'annual', 'calculate', 'yrs-repeat'))[$s['final']]);
        $this->assertSame($versions, $this->rowsOf('annual_results', $s['final']));
    }

    #[Test]
    public function invalid_targets_are_refused_and_process_nobody(): void
    {
        $s = $this->sceneWithTermResults();
        $otherYear = $this->createAcademicYear('AY-YRS-OTHER');
        $otherClass = (int) $this->createClassForSchool($s['school'], $otherYear)->id;

        $this->submit($s, 'annual', 'calculate', 'yrs-c', ['class_id' => $otherClass])->assertSessionHas('error', 'results.batch.class_invalid');
        $this->submit($s, 'annual', 'calculate', 'yrs-s', ['section_id' => (int) $this->createSectionForClass($otherClass)->id])->assertSessionHas('error', 'results.batch.section_invalid');
        $this->submit($s, 'ranking', 'calculate', 'yrs-t')->assertSessionHasErrors('target');
        $this->submit($s, 'annual', 'calculate', 'yrs-e', ['enrollment_ids' => [$s['final']]])->assertSessionHasErrors('enrollment_ids');
        $this->from('/results/year-sheet')->post('/results/year-sheet/calculate', ['academic_year_id' => $s['year_id'], 'class_id' => $s['class'], 'target' => 'annual'])
            ->assertSessionHas('error');

        $this->assertSame(0, DB::table(SchemaHelper::qualified('results', 'annual_results'))->where('school_id', $s['school'])->count());
    }

    #[Test]
    public function only_results_managers_may_calculate_or_finalize(): void
    {
        $s = $this->scene();
        $this->as('ResultsViewer', $s['school']);
        $this->get('/results/year-sheet?academic_year_id='.$s['year_id'])->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('abilities.calculate', false)->where('abilities.finalize', false));
        $this->submit($s, 'annual', 'calculate', 'yrs-v1')->assertForbidden();
        $this->submit($s, 'gpa', 'finalize', 'yrs-v2')->assertForbidden();

        $this->as('GradesTeacher', $s['school']);
        $this->get('/results/year-sheet?academic_year_id='.$s['year_id'])->assertForbidden();
        $this->submit($s, 'annual', 'calculate', 'yrs-t1')->assertForbidden();

        $this->assertSame(0, DB::table(SchemaHelper::qualified('results', 'annual_results'))->where('school_id', $s['school'])->count());
    }

    #[Test]
    public function the_sheet_lists_the_class_with_term_annual_and_gpa_results(): void
    {
        $s = $this->sceneWithTermResults();
        $this->submit($s, 'annual', 'calculate', 'yrs-page');

        $this->get("/results/year-sheet?academic_year_id={$s['year_id']}&class_id={$s['class']}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('results/year-sheet')
                ->where('abilities.calculate', true)->where('abilities.finalize', true)
                ->has('rows', 3)
                ->where('rows', function ($rows) use ($s): bool {
                    $final = collect($rows)->firstWhere('enrollment_id', $s['final']);
                    $ungraded = collect($rows)->firstWhere('enrollment_id', $s['ungraded']);

                    return $final['term_results'] === ['operational' => 1, 'official' => 1]
                        && $final['annual']['operational']['value'] === '80.00'
                        && $final['annual']['official'] === null && $final['gpa']['operational'] === null
                        && $ungraded['term_results'] === ['operational' => 0, 'official' => 0];
                }));
    }
}
