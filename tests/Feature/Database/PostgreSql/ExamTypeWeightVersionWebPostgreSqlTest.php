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
 * EXAMIS C — per-year weight versions of exam types: INSERT-only versions with a reason, the newest version of the year
 * is used by calculations from then on and is pinned in the result, stored results are never recalculated.
 */
final class ExamTypeWeightVersionWebPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsResultSheetScene;

    private function versions(): string
    {
        return SchemaHelper::qualified('exams', 'exam_type_weight_versions');
    }

    /** @param  array<string, mixed>  $s */
    private function typeId(array $s): int
    {
        return (int) DB::table(SchemaHelper::qualified('exams', 'exam_types'))->where('school_id', $s['school'])->value('id');
    }

    /** @param  array<string, mixed>  $s */
    private function version(array $s, int $weight, string $key, string $reason = 'قرار مجلس الإدارة'): TestResponse
    {
        return $this->from('/exams/types')->post('/exams/types/'.$this->typeId($s).'/weights', [
            'academic_year_id' => $s['year_id'], 'weight_percentage' => $weight, 'reason' => $reason,
        ], ['X-Idempotency-Key' => $key]);
    }

    /** @param  array<string, mixed>  $s */
    private function calculate(array $s, string $key): TestResponse
    {
        return $this->from('/results/sheet')->post('/results/sheet/calculate', [
            'academic_year_id' => $s['year_id'], 'term_id' => $s['term_id'], 'subject_id' => $s['subject_id'], 'class_id' => $s['class'],
        ], ['X-Idempotency-Key' => $key]);
    }

    #[Test]
    public function a_version_is_numbered_per_type_and_year_needs_a_reason_and_replays_by_key(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);

        $this->version($s, 100, 'wv-1')->assertSessionHas('success', 'flash.exams.typeWeightVersion');
        $this->version($s, 100, 'wv-1')->assertSessionHas('success');
        $this->version($s, 90, 'wv-2')->assertSessionHas('success');
        $this->assertSame([1, 2], DB::table($this->versions())->orderBy('version_no')->pluck('version_no')->map(fn ($v) => (int) $v)->all());

        $this->version($s, 80, 'wv-3', '')->assertSessionHasErrors('reason');
        $this->version($s, 120, 'wv-4')->assertSessionHasErrors('weight_percentage');
        $this->assertSame(2, DB::table($this->versions())->count());
    }

    #[Test]
    public function only_the_type_manager_creates_versions_and_the_page_shows_the_year_weight(): void
    {
        $s = $this->scene();
        $this->as('GradesViewer', $s['school']);
        $this->version($s, 90, 'wv-v1')->assertForbidden();
        $this->as('GradesTeacher', $s['school']);
        $this->version($s, 90, 'wv-v2')->assertForbidden();
        $this->assertSame(0, DB::table($this->versions())->count());

        $this->as('GradesManager', $s['school']);
        $this->version($s, 90, 'wv-m1')->assertSessionHas('success');
        $this->get("/exams/types?academic_year_id={$s['year_id']}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('types.data.0.weight_percentage', 100)->where('types.data.0.year_weight', 90)->where('types.data.0.year_version', 1)
            ->has('types.data.0.history', 1));
    }

    #[Test]
    public function the_database_refuses_edits_and_deletes_of_a_version(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);
        $this->version($s, 90, 'wv-db');
        $id = (int) DB::table($this->versions())->value('id');

        foreach ([
            fn () => DB::table($this->versions())->where('id', $id)->update(['weight_percentage' => 10]),
            fn () => DB::table($this->versions())->where('id', $id)->delete(),
        ] as $change) {
            try {
                DB::transaction($change);
                $this->fail('the database must refuse');
            } catch (QueryException $e) {
                $this->assertStringContainsString('insert-only', $e->getMessage());
            }
        }
    }

    #[Test]
    public function calculation_uses_the_newest_version_of_the_year_and_pins_it(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);
        $this->version($s, 50, 'wv-c1');
        $this->as('ResultsManager', $s['school']);
        $this->calculate($s, 'wv-calc-1')->assertSessionHas('error', 'flash.results.batch.partial');
        $this->assertSame(0, DB::table(SchemaHelper::qualified('results', 'term_results'))->where('school_id', $s['school'])->count(), 'weights of 50 do not total 100: nothing is stored');

        $this->as('GradesManager', $s['school']);
        $this->version($s, 100, 'wv-c2');
        $this->as('ResultsManager', $s['school']);
        $this->calculate($s, 'wv-calc-2')->assertSessionHas('error');
        $row = DB::table(SchemaHelper::qualified('results', 'term_results'))->where('enrollment_id', $s['final'])->where('is_current_operational', true)->first();
        $this->assertNotNull($row);
        $pin = json_decode((string) $row->policy_pin, true);
        $this->assertSame(['weight' => 100, 'version' => 2], $pin['weights'][(string) $this->typeId($s)]);
    }

    #[Test]
    public function creating_a_version_never_recalculates_a_stored_result(): void
    {
        $s = $this->scene();
        $this->as('ResultsManager', $s['school']);
        $this->calculate($s, 'wv-s1');
        $table = SchemaHelper::qualified('results', 'term_results');
        $before = DB::table($table)->where('school_id', $s['school'])->orderBy('id')->get(['id', 'result_version', 'weighted_total', 'source_fingerprint'])->map(fn ($r) => (array) $r)->all();
        $this->assertNotEmpty($before);

        $this->as('GradesManager', $s['school']);
        $this->version($s, 40, 'wv-s2');

        $after = DB::table($table)->where('school_id', $s['school'])->orderBy('id')->get(['id', 'result_version', 'weighted_total', 'source_fingerprint'])->map(fn ($r) => (array) $r)->all();
        $this->assertSame($before, $after);
    }

    #[Test]
    public function a_version_of_one_school_cannot_be_made_for_another_schools_type(): void
    {
        $s = $this->scene();
        $other = $this->createSchool('SCH-OTHER-WV', 'Other school');
        $this->as('GradesManager', $other);

        $this->version($s, 90, 'wv-x')->assertNotFound();
        $this->assertSame(0, DB::table($this->versions())->count());
    }
}
