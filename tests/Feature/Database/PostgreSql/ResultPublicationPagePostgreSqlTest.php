<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Results\Contracts\ResultPublicationRepositoryInterface;
use App\Database\SchemaHelper;
use App\Models\User;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
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
 * EXAMIS U-01 — «نشر النتائج السنوية» (/results/publications): the web entry point of the existing publish / withdraw
 * handlers. Covers permission, school isolation, kind validation, all-or-nothing, duplicates and the race loser, a
 * stable snapshot with drift, withdrawal with a reason and replay, the audit trail, and what the student portal shows.
 */
final class ResultPublicationPagePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsOfficialYearScene;
    use SeedsResultSheetScene;

    private const URL = '/results/publications';

    private function table(): string
    {
        return SchemaHelper::qualified('results', 'result_publications');
    }

    /** @param  array<string, mixed>  $s */
    private function publish(array $s, array $overrides = []): TestResponse
    {
        return $this->from(self::URL.'?academic_year_id='.$s['year_id'])->post(self::URL, $overrides + [
            'academic_year_id' => $s['year_id'], 'class_id' => $s['class'], 'section_id' => $s['section'], 'target' => 'annual',
        ]);
    }

    private function withdraw(int $id, string $reason, string $key): TestResponse
    {
        return $this->from(self::URL)->post(self::URL."/{$id}/withdraw", ['reason' => $reason], ['X-Idempotency-Key' => $key]);
    }

    /**
     * Acts as a portal user scoped to the student of the enrollment.
     *
     * @return array{string, User} the portal URL of the official annual result, and the user
     */
    private function actAsStudentPortal(array $s): array
    {
        $user = $this->actingAsPortalResultsViewerForSchool($s['school']);
        $studentId = (int) DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->where('id', $s['final'])->value('student_id');
        DB::table(SchemaHelper::qualified('security', 'scopes'))->insert([
            'user_id' => $user->id, 'scope_type' => PortalScopeType::STUDENT, 'scope_id' => $studentId, 'created_at' => now(),
        ]);

        return [sprintf('/api/v1/portal/results/annual?enrollment_id=%d&academic_year_id=%d', $s['final'], $s['year_id']), $user];
    }

    #[Test]
    public function the_page_lists_the_scope_with_its_readiness_and_the_publish_ability(): void
    {
        $s = $this->officialScene();
        $this->as('ResultsPublisher', $s['school']);

        $this->get(self::URL."?academic_year_id={$s['year_id']}&class_id={$s['class']}&section_id={$s['section']}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('results/publications')
                ->has('rows', 1)
                ->where('rows.0.enrollment_id', $s['final'])
                ->where('rows.0.annual.incomplete', false)
                ->where('reconciliation.ready.annual', true)
                ->where('reconciliation.ready.gpa', true)
                ->where('abilities.publish', true)
                ->has('publications', 0));

        $this->get(self::URL."?academic_year_id={$s['year_id']}&class_id={$s['class']}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('rows', 3)->where('reconciliation.ready.annual', false));
    }

    #[Test]
    public function publishing_a_complete_scope_stores_one_snapshot_and_a_repeat_is_refused(): void
    {
        $s = $this->officialScene();
        $annualBefore = DB::table(SchemaHelper::qualified('results', 'annual_results'))->orderBy('id')->get()->toArray();
        $user = $this->as('ResultsPublisher', $s['school']);

        $this->publish($s)->assertSessionHas('success', 'flash.results.year.annual.publish');
        $this->publish($s)->assertSessionHas('error', 'results.publish.already_published');
        $this->publish($s, ['target' => 'gpa'])->assertSessionHas('success', 'flash.results.year.gpa.publish');

        $rows = DB::table($this->table())->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $annual = $rows[0];
        $this->assertSame(['annual', 'published', $s['section'], (int) $user->id, 1], [$annual->kind, $annual->status, (int) $annual->section_id, (int) $annual->published_by, (int) $annual->participant_count]);
        $this->assertSame(64, strlen($annual->source_fingerprint));
        $snapshot = json_decode($annual->published_snapshot, true);
        $this->assertSame($s['final'], $snapshot[0]['enrollment_id']);
        $this->assertEquals($annualBefore, DB::table(SchemaHelper::qualified('results', 'annual_results'))->orderBy('id')->get()->toArray(), 'result rows are untouched');
    }

    #[Test]
    public function an_incomplete_scope_publishes_nothing(): void
    {
        $s = $this->officialScene();
        $this->as('ResultsPublisher', $s['school']);

        $this->publish($s, ['section_id' => null])->assertSessionHas('error', 'results.publish.not_ready');
        $this->publish($s, ['section_id' => $s['elsewhere']])->assertSessionHas('error', 'results.publish.not_ready');
        $this->assertSame(0, DB::table($this->table())->count());
    }

    #[Test]
    public function an_official_result_marked_incomplete_makes_the_scope_not_ready(): void
    {
        $s = $this->officialScene();
        DB::table(SchemaHelper::qualified('results', 'annual_results'))->where('enrollment_id', $s['final'])->where('is_current_official', true)->update(['incomplete' => true]);
        $this->as('ResultsPublisher', $s['school']);

        $this->get(self::URL."?academic_year_id={$s['year_id']}&class_id={$s['class']}&section_id={$s['section']}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('rows.0.annual.incomplete', true)->where('reconciliation.ready.annual', false));
        $this->publish($s)->assertSessionHas('error', 'results.publish.not_ready');
        $this->publish($s, ['target' => 'gpa'])->assertSessionHas('success', 'flash.results.year.gpa.publish');
        $this->assertSame(['gpa'], DB::table($this->table())->pluck('kind')->all());
    }

    #[Test]
    public function only_the_publish_permission_publishes_or_withdraws(): void
    {
        $s = $this->officialScene();
        $id = $this->publishAs($s);

        foreach (['ResultsManager', 'ResultsViewer'] as $role) {
            $this->as($role, $s['school']);
            $this->publish($s, ['target' => 'gpa'])->assertForbidden();
            $this->withdraw($id, 'سبب كافٍ', 'k-'.$role)->assertForbidden();
        }
        $this->as('ResultsManager', $s['school']);
        $this->get(self::URL."?academic_year_id={$s['year_id']}&class_id={$s['class']}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('abilities.publish', false)->has('publications', 1));

        $this->assertSame(1, DB::table($this->table())->count());
        $this->assertSame('published', DB::table($this->table())->where('id', $id)->value('status'));
    }

    #[Test]
    public function another_school_cannot_publish_or_withdraw_or_see_the_scope(): void
    {
        $s = $this->officialScene();
        $id = $this->publishAs($s);
        $other = $this->createSchool('SCH-U01-OTHER', 'Other school');
        $this->as('ResultsPublisher', $other);

        $this->publish($s, ['target' => 'gpa'])->assertSessionHas('error');
        $this->withdraw($id, 'محاولة من مدرسة أخرى', 'k-other')->assertNotFound();
        $this->get(self::URL."?academic_year_id={$s['year_id']}&class_id={$s['class']}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('rows', 0)->has('publications', 0));

        $this->assertSame(1, DB::table($this->table())->count());
        $this->assertSame('published', DB::table($this->table())->where('id', $id)->value('status'));
    }

    #[Test]
    public function the_result_kind_is_validated(): void
    {
        $s = $this->officialScene();
        $this->as('ResultsPublisher', $s['school']);

        $this->publish($s, ['target' => 'term'])->assertSessionHasErrors('target');
        $this->publish($s, ['target' => 'bogus'])->assertSessionHasErrors('target');
        $this->publish($s, ['enrollment_id' => $s['final']])->assertSessionHasErrors('enrollment_id');
        $this->assertSame(0, DB::table($this->table())->count());
    }

    #[Test]
    public function the_loser_of_a_race_past_the_overlap_check_gets_already_published_and_no_second_row(): void
    {
        $s = $this->officialScene();
        // Both requests "pass" the overlap check, as two parallel requests would; the partial unique index decides.
        $this->decorateRepository(overlapping: static fn (): array => []);
        $this->as('ResultsPublisher', $s['school']);

        $this->publish($s)->assertSessionHas('success');
        $this->publish($s)->assertSessionHas('error', 'results.publish.already_published');
        $this->assertSame(1, DB::table($this->table())->where('status', 'published')->count());
    }

    #[Test]
    public function a_rebuild_between_building_the_snapshot_and_locking_it_refuses_the_stale_snapshot(): void
    {
        $s = $this->officialScene();
        // R-08: a rebuild commits after the snapshot was read but before its rows are locked — the real lock check must
        // see that the published version is no longer the current official one.
        $this->decorateRepository(beforeLock: function () use ($s): void {
            DB::table(SchemaHelper::qualified('results', 'annual_results'))->where('enrollment_id', $s['final'])->where('is_current_official', true)
                ->update(['result_version' => DB::raw('result_version + 10')]);
        });
        $this->as('ResultsPublisher', $s['school']);

        $this->publish($s)->assertSessionHas('error', 'results.publish.changed');
        $this->assertSame(0, DB::table($this->table())->count());
    }

    #[Test]
    public function a_withdraw_key_reused_for_another_publication_is_refused_not_replayed(): void
    {
        $s = $this->officialScene();
        $annual = $this->publishAs($s);
        $this->publish($s, ['target' => 'gpa'])->assertSessionHas('success');
        $gpa = (int) DB::table($this->table())->where('kind', 'gpa')->value('id');

        $this->withdraw($annual, 'سبب السحب الأول', 'same-key')->assertSessionHas('success', 'flash.results.year.withdraw');
        // R-07: the same key for the GPA publication must not replay the annual withdrawal as if it had succeeded.
        $this->withdraw($gpa, 'سبب السحب الثاني', 'same-key')->assertSessionHas('error', 'results.publish.idempotency_conflict');
        $this->withdraw($annual, 'سبب السحب الأول', 'same-key')->assertSessionHas('success', 'flash.results.year.withdraw');

        $this->assertSame('withdrawn', DB::table($this->table())->where('id', $annual)->value('status'));
        $this->assertSame('published', DB::table($this->table())->where('id', $gpa)->value('status'));
    }

    /**
     * Replaces the publication repository by a pass-through decorator: `overlapping` replaces the overlap check,
     * `beforeLock` runs just before the real lock of the snapshot rows.
     */
    private function decorateRepository(?Closure $overlapping = null, ?Closure $beforeLock = null): void
    {
        $real = app(ResultPublicationRepositoryInterface::class);
        $this->app->instance(ResultPublicationRepositoryInterface::class, new class($real, $overlapping, $beforeLock) implements ResultPublicationRepositoryInterface
        {
            public function __construct(
                private readonly ResultPublicationRepositoryInterface $inner,
                private readonly ?Closure $overlapping,
                private readonly ?Closure $beforeLock,
            ) {}

            public function activeOverlapping(int $schoolId, int $academicYearId, int $classId, ?int $sectionId, string $kind, ?int $termId = null, ?int $subjectId = null): array
            {
                return $this->overlapping !== null
                    ? ($this->overlapping)()
                    : $this->inner->activeOverlapping($schoolId, $academicYearId, $classId, $sectionId, $kind, $termId, $subjectId);
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

    #[Test]
    public function withdrawal_needs_a_reason_replays_with_the_same_key_and_hides_the_result_from_the_student(): void
    {
        $s = $this->officialScene();
        $id = $this->publishAs($s);
        [$portal, $student] = $this->actAsStudentPortal($s);
        $this->getJson($portal)->assertOk()->assertJsonPath('data.enrollment_id', $s['final']);

        $this->flushHeaders();
        $user = $this->as('ResultsPublisher', $s['school']);
        $this->withdraw($id, '', 'w0')->assertSessionHasErrors('reason');
        $this->withdraw($id, 'خطأ في إدخال الدرجات', 'w1')->assertSessionHas('success', 'flash.results.year.withdraw');
        $this->withdraw($id, 'خطأ في إدخال الدرجات', 'w1')->assertSessionHas('success', 'flash.results.year.withdraw');
        $this->withdraw($id, 'مرة ثانية', 'w2')->assertNotFound();

        $row = DB::table($this->table())->where('id', $id)->first();
        $this->assertSame(['withdrawn', 'خطأ في إدخال الدرجات', (int) $user->id], [$row->status, $row->withdraw_reason, (int) $row->withdrawn_by]);
        $this->assertNotNull($row->withdrawn_at);

        Sanctum::actingAs($student);
        $this->withHeader('X-School-Id', (string) $s['school']);
        $this->getJson($portal)->assertNotFound();

        $this->flushHeaders();
        $this->as('ResultsPublisher', $s['school']);
        $this->publish($s)->assertSessionHas('success', 'flash.results.year.annual.publish');
        $this->assertSame(2, DB::table($this->table())->count());
    }

    #[Test]
    public function the_snapshot_stays_as_published_and_a_later_official_change_is_reported_and_hidden(): void
    {
        $s = $this->officialScene();
        $id = $this->publishAs($s);
        $stored = DB::table($this->table())->where('id', $id)->value('published_snapshot');

        // Simulates a later official rebuild: the current official row no longer has the published version.
        DB::table(SchemaHelper::qualified('results', 'annual_results'))->where('enrollment_id', $s['final'])->where('is_current_official', true)
            ->update(['result_version' => DB::raw('result_version + 10')]);

        $this->get(self::URL."?academic_year_id={$s['year_id']}&class_id={$s['class']}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('publications.0.id', $id)->where('publications.0.drifted', 1));
        $this->assertSame($stored, DB::table($this->table())->where('id', $id)->value('published_snapshot'));

        $this->getJson($this->actAsStudentPortal($s)[0])->assertNotFound();
    }

    #[Test]
    public function publish_and_withdraw_are_written_to_the_security_audit(): void
    {
        $s = $this->officialScene();
        $events = [];
        $this->app->instance(SecurityAuditLoggerInterface::class, new class($events) implements SecurityAuditLoggerInterface
        {
            /** @param  list<array{string, string, string}>  $events */
            public function __construct(private array &$events) {}

            public function record(SecurityEventType $event, string $action, string $result, ?User $actor = null, ?string $target = null, array $context = []): void
            {
                $this->events[] = [$event->value, $action, $result];
            }
        });
        $this->as('ResultsPublisher', $s['school']);

        $this->publish($s)->assertSessionHas('success');
        $id = (int) DB::table($this->table())->value('id');
        $this->withdraw($id, 'سبب السحب', 'audit-w')->assertSessionHas('success');

        $this->assertContains([SecurityEventType::ResultsDataModified->value, 'results.web.publications.annual.publish', 'published'], $events);
        $this->assertContains([SecurityEventType::ResultsDataModified->value, 'results.web.publications.withdraw', 'withdrawn'], $events);
    }

    /** @param  array<string, mixed>  $s */
    private function publishAs(array $s): int
    {
        $this->as('ResultsPublisher', $s['school']);
        $this->publish($s)->assertSessionHas('success', 'flash.results.year.annual.publish');

        return (int) DB::table($this->table())->where('kind', 'annual')->where('status', 'published')->value('id');
    }
}
