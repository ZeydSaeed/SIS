<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Results\Contracts\ResultPublicationRepositoryInterface;
use App\Database\SchemaHelper;
use App\Infrastructure\Persistence\Results\EloquentResultPublicationRepository;
use App\Models\User;
use App\Security\Authorization\PortalScopeType;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Results\SeedsOfficialYearScene;
use Tests\Support\Results\SeedsResultSheetScene;

/**
 * EXAMIS U-03 — publication of official term results («نشر نتائج الفصول», /results/term-publications) and of issued
 * transcripts (target=transcript on /results/publications), through the existing handlers: completeness, duplicates,
 * permission, school isolation, a stable snapshot, withdrawal, idempotency, the race with a rebuild, and «نتائجي».
 */
final class TermAndTranscriptPublicationPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsOfficialYearScene;
    use SeedsResultSheetScene;

    private const TERM_URL = '/results/term-publications';

    private function table(): string
    {
        return SchemaHelper::qualified('results', 'result_publications');
    }

    /** @param  array<string, mixed>  $s */
    private function publishTerm(array $s, array $overrides = []): TestResponse
    {
        return $this->from(self::TERM_URL)->post(self::TERM_URL, $overrides + [
            'academic_year_id' => $s['year_id'], 'term_id' => $s['term_id'], 'subject_id' => $s['subject_id'],
            'class_id' => $s['class'], 'section_id' => $s['section'],
        ]);
    }

    /** @param  array<string, mixed>  $s */
    private function publishTranscripts(array $s, array $overrides = []): TestResponse
    {
        return $this->from('/results/publications')->post('/results/publications', $overrides + [
            'academic_year_id' => $s['year_id'], 'class_id' => $s['class'], 'section_id' => $s['section'], 'target' => 'transcript',
        ]);
    }

    private function withdraw(int $id, string $key): TestResponse
    {
        return $this->from('/results/publications')->post("/results/publications/{$id}/withdraw", ['reason' => 'سحب للاختبار'], ['X-Idempotency-Key' => $key]);
    }

    /** @param  array<string, mixed>  $s */
    private function termUrl(array $s): string
    {
        return self::TERM_URL."?academic_year_id={$s['year_id']}&term_id={$s['term_id']}&subject_id={$s['subject_id']}&class_id={$s['class']}&section_id={$s['section']}";
    }

    /** Issues the transcript of the finalized student through the results API (needs the official GPA). */
    private function issueTranscript(array $s): void
    {
        $this->flushHeaders();
        $this->actingAsResultsManagerForSchool($s['school']);
        $this->postJson('/api/v1/results/transcripts/issue', ['enrollment_id' => $s['final'], 'academic_year_id' => $s['year_id']], ['X-Idempotency-Key' => 'u03-tr'])
            ->assertSuccessful();
        $this->flushHeaders();
    }

    private function portalStudent(array $s): User
    {
        $this->flushHeaders();
        $user = $this->actingAsPortalResultsViewerForSchool($s['school']);
        DB::table(SchemaHelper::qualified('security', 'scopes'))->insert([
            'user_id' => $user->id, 'scope_type' => PortalScopeType::STUDENT, 'scope_id' => $this->studentOf($s['final']), 'created_at' => now(),
        ]);

        return $user;
    }

    private function actAs(User $user, int $schoolId): void
    {
        $this->flushHeaders();
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);
    }

    /** Pass-through repository; `beforeLock` runs just before the real lock, `overlapping` replaces the overlap check. */
    private function decorateRepository(?Closure $beforeLock = null, ?Closure $overlapping = null): void
    {
        // Always the real repository, never an earlier decorator (a second call must not stack the first one's hooks).
        $real = app(EloquentResultPublicationRepository::class);
        $this->app->instance(ResultPublicationRepositoryInterface::class, new class($real, $beforeLock, $overlapping) implements ResultPublicationRepositoryInterface
        {
            public function __construct(private readonly ResultPublicationRepositoryInterface $inner, private readonly ?Closure $beforeLock, private readonly ?Closure $overlapping) {}

            public function activeOverlapping(int $schoolId, int $academicYearId, int $classId, ?int $sectionId, string $kind, ?int $termId = null, ?int $subjectId = null): array
            {
                return $this->overlapping !== null ? ($this->overlapping)() : $this->inner->activeOverlapping($schoolId, $academicYearId, $classId, $sectionId, $kind, $termId, $subjectId);
            }

            public function lockSnapshot(int $schoolId, int $academicYearId, string $kind, array $snapshot, ?int $termId = null, ?int $subjectId = null): bool
            {
                if ($this->beforeLock !== null) {
                    ($this->beforeLock)();
                }

                return $this->inner->lockSnapshot($schoolId, $academicYearId, $kind, $snapshot, $termId, $subjectId);
            }

            public function insert(int $schoolId, int $academicYearId, int $classId, ?int $sectionId, string $kind, ?int $termId, ?int $subjectId, array $snapshot, string $fingerprint, ?int $userId, ?string $correlationId): int
            {
                return $this->inner->insert($schoolId, $academicYearId, $classId, $sectionId, $kind, $termId, $subjectId, $snapshot, $fingerprint, $userId, $correlationId);
            }

            public function withdraw(int $schoolId, int $publicationId, ?int $userId, string $reason): bool
            {
                return $this->inner->withdraw($schoolId, $publicationId, $userId, $reason);
            }

            public function isPublished(int $schoolId, int $academicYearId, int $enrollmentId, string $kind, int $version, ?int $termId = null, ?int $subjectId = null): bool
            {
                return $this->inner->isPublished($schoolId, $academicYearId, $enrollmentId, $kind, $version, $termId, $subjectId);
            }

            public function activeWithDrift(int $schoolId): array
            {
                return $this->inner->activeWithDrift($schoolId);
            }

            public function activeForClass(int $schoolId, int $academicYearId, int $classId): array
            {
                return $this->inner->activeForClass($schoolId, $academicYearId, $classId);
            }
        });
    }

    // ── Term results ────────────────────────────────────────────────────────────────────────────────────────────

    #[Test]
    public function the_term_page_lists_the_scope_with_pickers_and_readiness(): void
    {
        $s = $this->officialScene();
        $this->as('ResultsPublisher', $s['school']);

        $this->get($this->termUrl($s))->assertOk()->assertInertia(fn (Assert $page) => $page->component('results/term-publications')
            ->where('terms.0.id', $s['term_id'])
            ->where('subjects.0.id', $s['subject_id'])
            ->has('rows', 1)
            ->where('rows.0.enrollment_id', $s['final'])
            ->where('reconciliation.ready.term', true)
            ->where('abilities.publish', true)
            ->has('publications', 0));
    }

    #[Test]
    public function a_complete_term_scope_is_published_once_with_its_snapshot(): void
    {
        $s = $this->officialScene();
        $termBefore = DB::table(SchemaHelper::qualified('results', 'term_results'))->orderBy('id')->get()->toArray();
        $user = $this->as('ResultsPublisher', $s['school']);

        $this->publishTerm($s)->assertSessionHas('success', 'flash.results.batch.publish');
        $this->publishTerm($s)->assertSessionHas('error', 'results.publish.already_published');

        $row = DB::table($this->table())->sole();
        $this->assertSame(['term', $s['term_id'], $s['subject_id'], $s['section'], (int) $user->id], [$row->kind, (int) $row->term_id, (int) $row->subject_id, (int) $row->section_id, (int) $row->published_by]);
        $version = (int) DB::table(SchemaHelper::qualified('results', 'term_results'))->where('enrollment_id', $s['final'])->where('is_current_official', true)->value('result_version');
        $this->assertSame([['enrollment_id' => $s['final'], 'version' => $version]], array_map(static fn (array $e): array => ['enrollment_id' => $e['enrollment_id'], 'version' => $e['version']], json_decode($row->published_snapshot, true)));
        $this->assertEquals($termBefore, DB::table(SchemaHelper::qualified('results', 'term_results'))->orderBy('id')->get()->toArray(), 'term result rows are untouched');
    }

    #[Test]
    public function an_incomplete_or_invalid_term_scope_publishes_nothing(): void
    {
        $s = $this->officialScene();
        $this->as('ResultsPublisher', $s['school']);

        $this->publishTerm($s, ['section_id' => null])->assertSessionHas('error', 'results.publish.not_ready');
        $this->publishTerm($s, ['term_id' => 999999])->assertSessionHas('error', 'results.publish.scope_invalid');
        $this->publishTerm($s, ['enrollment_id' => $s['final']])->assertSessionHasErrors('enrollment_id');
        $this->publishTerm($s, ['term_id' => null])->assertSessionHasErrors('term_id');
        $this->assertSame(0, DB::table($this->table())->count());
    }

    #[Test]
    public function only_the_publish_permission_publishes_term_results(): void
    {
        $s = $this->officialScene();
        foreach (['ResultsManager', 'ResultsViewer'] as $role) {
            $this->as($role, $s['school']);
            $this->publishTerm($s)->assertForbidden();
        }
        $this->get($this->termUrl($s))->assertOk()->assertInertia(fn (Assert $page) => $page->where('abilities.publish', false));
        $this->assertSame(0, DB::table($this->table())->count());
    }

    #[Test]
    public function another_school_cannot_publish_withdraw_or_see_term_results(): void
    {
        $s = $this->officialScene();
        $this->as('ResultsPublisher', $s['school']);
        $this->publishTerm($s)->assertSessionHas('success');
        $id = (int) DB::table($this->table())->value('id');
        $other = $this->createSchool('SCH-U03-OTHER', 'Other school');
        $this->as('ResultsPublisher', $other);

        $this->publishTerm($s)->assertSessionHas('error', 'results.batch.class_invalid');
        $this->withdraw($id, 'u03-other')->assertNotFound();
        $this->get($this->termUrl($s))->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows', 0)->has('publications', 0));
        $this->assertSame([1, 'published'], [DB::table($this->table())->count(), DB::table($this->table())->value('status')]);
    }

    #[Test]
    public function the_term_snapshot_is_stable_drift_is_reported_and_the_newer_version_hidden(): void
    {
        $s = $this->officialScene();
        $this->as('ResultsPublisher', $s['school']);
        $this->publishTerm($s)->assertSessionHas('success');
        $stored = DB::table($this->table())->value('published_snapshot');
        $student = $this->portalStudent($s);
        $this->get('/portal/results')->assertInertia(fn (Assert $page) => $page->has('rows', 1)->has('rows.0.terms', 1));

        DB::table(SchemaHelper::qualified('results', 'term_results'))->where('enrollment_id', $s['final'])->where('is_current_official', true)
            ->update(['result_version' => DB::raw('result_version + 10')]);

        $this->get('/portal/results')->assertInertia(fn (Assert $page) => $page->has('rows', 0));
        $this->flushHeaders();
        $this->as('ResultsPublisher', $s['school']);
        $this->get($this->termUrl($s))->assertInertia(fn (Assert $page) => $page->where('publications.0.drifted', 1));
        $this->assertSame($stored, DB::table($this->table())->value('published_snapshot'));
        $this->assertNotNull($student);
    }

    #[Test]
    public function a_term_publication_is_withdrawn_with_a_reason_replayed_with_its_key_and_hidden_from_the_student(): void
    {
        $s = $this->officialScene();
        $this->as('ResultsPublisher', $s['school']);
        $this->publishTerm($s)->assertSessionHas('success');
        $id = (int) DB::table($this->table())->value('id');
        $student = $this->portalStudent($s);
        $this->get('/portal/results')->assertInertia(fn (Assert $page) => $page->has('rows.0.terms', 1));

        $this->flushHeaders();
        $this->as('ResultsPublisher', $s['school']);
        $this->withdraw($id, 'u03-w')->assertSessionHas('success', 'flash.results.year.withdraw');
        $this->withdraw($id, 'u03-w')->assertSessionHas('success', 'flash.results.year.withdraw');
        $this->assertSame(['withdrawn', 'سحب للاختبار'], [DB::table($this->table())->value('status'), DB::table($this->table())->value('withdraw_reason')]);

        $this->actAs($student, $s['school']);
        $this->get('/portal/results')->assertInertia(fn (Assert $page) => $page->has('rows', 0));
        $this->getJson(sprintf('/api/v1/portal/results/term?enrollment_id=%d&academic_year_id=%d&term_id=%d&subject_id=%d', $s['final'], $s['year_id'], $s['term_id'], $s['subject_id']))
            ->assertNotFound();
    }

    #[Test]
    public function a_rebuild_racing_the_term_publication_is_refused_and_the_overlap_race_loser_gets_already_published(): void
    {
        $s = $this->officialScene();
        $this->decorateRepository(beforeLock: function () use ($s): void {
            DB::table(SchemaHelper::qualified('results', 'term_results'))->where('enrollment_id', $s['final'])->where('is_current_official', true)
                ->update(['result_version' => DB::raw('result_version + 10')]);
        });
        $this->as('ResultsPublisher', $s['school']);
        $this->publishTerm($s)->assertSessionHas('error', 'results.publish.changed');
        $this->assertSame(0, DB::table($this->table())->count());

        $this->decorateRepository(overlapping: static fn (): array => []);
        $this->publishTerm($s)->assertSessionHas('success');
        $this->publishTerm($s)->assertSessionHas('error', 'results.publish.already_published');
        $this->assertSame(1, DB::table($this->table())->count());
    }

    #[Test]
    public function the_portal_shows_only_the_published_term_of_the_signed_in_student(): void
    {
        $s = $this->officialScene();
        $this->portalStudent($s);
        $this->get('/portal/results')->assertInertia(fn (Assert $page) => $page->has('rows', 0));

        $this->flushHeaders();
        $this->as('ResultsPublisher', $s['school']);
        $this->publishTerm($s)->assertSessionHas('success');
        // Another student of the class, seated but not published: a parameter naming the published one changes nothing.
        $this->flushHeaders();
        $other = $this->actingAsPortalResultsViewerForSchool($s['school']);
        DB::table(SchemaHelper::qualified('security', 'scopes'))->insert([
            'user_id' => $other->id, 'scope_type' => PortalScopeType::STUDENT, 'scope_id' => $this->studentOf($s['entered']), 'created_at' => now(),
        ]);
        $this->get('/portal/results?'.http_build_query(['enrollment_id' => $s['final'], 'student_id' => $this->studentOf($s['final'])]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows', 0));

        $this->portalStudent($s);
        $this->get('/portal/results')->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.terms.0.incomplete', false)->where('rows.0.annual', null));
    }

    // ── Transcripts ─────────────────────────────────────────────────────────────────────────────────────────────

    #[Test]
    public function an_issued_up_to_date_transcript_is_published_and_shown_to_the_student_only_while_published(): void
    {
        $s = $this->officialScene();
        $this->as('ResultsPublisher', $s['school']);
        $this->publishTranscripts($s)->assertSessionHas('error', 'results.publish.not_ready');

        $this->issueTranscript($s);
        $this->as('ResultsPublisher', $s['school']);
        $this->get("/results/publications?academic_year_id={$s['year_id']}&class_id={$s['class']}&section_id={$s['section']}")
            ->assertInertia(fn (Assert $page) => $page->where('rows.0.transcript.up_to_date', true)->where('reconciliation.ready.transcript', true));
        $this->publishTranscripts($s, ['section_id' => null])->assertSessionHas('error', 'results.publish.not_ready');
        $this->publishTranscripts($s)->assertSessionHas('success', 'flash.results.year.transcript.publish');
        $this->publishTranscripts($s)->assertSessionHas('error', 'results.publish.already_published');
        $pub = DB::table($this->table())->sole();
        $number = (string) DB::table(SchemaHelper::qualified('results', 'transcripts'))->where('enrollment_id', $s['final'])->where('is_current', true)->value('transcript_number');
        $this->assertSame(['transcript', $number], [$pub->kind, json_decode($pub->published_snapshot, true)[0]['value']]);

        $student = $this->portalStudent($s);
        $this->get('/portal/results')->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.transcript.value', $number)->where('rows.0.annual', null));
        $this->getJson(sprintf('/api/v1/portal/results/transcripts/issued?enrollment_id=%d&academic_year_id=%d', $s['final'], $s['year_id']))->assertOk();

        $this->flushHeaders();
        $this->as('ResultsPublisher', $s['school']);
        $this->withdraw((int) $pub->id, 'u03-tr-w')->assertSessionHas('success');
        $this->actAs($student, $s['school']);
        $this->get('/portal/results')->assertInertia(fn (Assert $page) => $page->has('rows', 0));
        $this->getJson(sprintf('/api/v1/portal/results/transcripts/issued?enrollment_id=%d&academic_year_id=%d', $s['final'], $s['year_id']))->assertNotFound();
    }

    #[Test]
    public function an_outdated_transcript_is_not_ready_and_a_reissue_racing_the_publication_is_refused(): void
    {
        $s = $this->officialScene();
        $this->issueTranscript($s);
        // The official GPA was finalized again after the transcript was issued: the transcript is no longer up to date.
        DB::table(SchemaHelper::qualified('results', 'gpa_results'))->where('enrollment_id', $s['final'])->where('is_current_official', true)
            ->update(['finalized_at' => now()->addDay()]);
        $this->as('ResultsPublisher', $s['school']);
        $this->publishTranscripts($s)->assertSessionHas('error', 'results.publish.not_ready');

        DB::table(SchemaHelper::qualified('results', 'gpa_results'))->where('enrollment_id', $s['final'])->where('is_current_official', true)
            ->update(['finalized_at' => now()->subDay()]);
        $this->decorateRepository(beforeLock: function () use ($s): void {
            DB::table(SchemaHelper::qualified('results', 'transcripts'))->where('enrollment_id', $s['final'])->where('is_current', true)
                ->update(['transcript_version' => DB::raw('transcript_version + 10')]);
        });
        $this->publishTranscripts($s)->assertSessionHas('error', 'results.publish.changed');
        $this->assertSame(0, DB::table($this->table())->count());
    }

    #[Test]
    public function only_the_publish_permission_publishes_transcripts_and_another_school_cannot(): void
    {
        $s = $this->officialScene();
        $this->issueTranscript($s);
        foreach (['ResultsManager', 'ResultsViewer'] as $role) {
            $this->as($role, $s['school']);
            $this->publishTranscripts($s)->assertForbidden();
        }
        $this->as('ResultsPublisher', $this->createSchool('SCH-U03-TR', 'Other school'));
        $this->publishTranscripts($s)->assertSessionHas('error', 'results.batch.class_invalid');
        $this->assertSame(0, DB::table($this->table())->count());
    }
}
