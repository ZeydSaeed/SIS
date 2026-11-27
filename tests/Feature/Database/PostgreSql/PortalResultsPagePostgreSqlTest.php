<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use App\Security\Authorization\PortalScopeType;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Results\SeedsOfficialYearScene;
use Tests\Support\Results\SeedsResultSheetScene;

/**
 * EXAMIS U-02 — «نتائجي» (/portal/results), through the real chain: official results → published on the U-01 page →
 * shown to the student. Only the signed-in user's own (or linked) students, only published versions, no parameter can
 * change who is shown, no other school, and the sidebar entry only for portal users.
 */
final class PortalResultsPagePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsOfficialYearScene;
    use SeedsResultSheetScene;

    /** Publishes the scene section's annual results (and GPA) as a results publisher. */
    private function publish(array $s, string $target = 'annual'): void
    {
        $this->flushHeaders();
        $this->as('ResultsPublisher', $s['school']);
        $this->from('/results/publications')->post('/results/publications', [
            'academic_year_id' => $s['year_id'], 'class_id' => $s['class'], 'section_id' => $s['section'], 'target' => $target,
        ])->assertSessionHas('success');
    }

    /** A portal user of the school with a scope (student or guardian) on the given student. */
    private function portalUser(int $schoolId, int $studentId, bool $guardian = false): User
    {
        $this->flushHeaders();
        $user = $this->actingAsPortalResultsViewerForSchool($schoolId);
        $scopeId = $studentId;
        if ($guardian) {
            $scopeId = (int) DB::table(SchemaHelper::qualified('guardians', 'guardians'))->insertGetId([
                'first_name' => 'Parent', 'last_name' => 'U02', 'full_name' => 'Parent U02', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table(SchemaHelper::qualified('guardians', 'student_guardians'))->insert([
                'student_id' => $studentId, 'guardian_id' => $scopeId, 'relationship_type' => 1, 'is_primary' => true,
                'is_emergency_contact' => false, 'created_at' => now(),
            ]);
        }
        DB::table(SchemaHelper::qualified('security', 'scopes'))->insert([
            'user_id' => $user->id, 'scope_type' => $guardian ? PortalScopeType::GUARDIAN : PortalScopeType::STUDENT, 'scope_id' => $scopeId, 'created_at' => now(),
        ]);

        return $user;
    }

    private function actAs(User $user, int $schoolId): void
    {
        $this->flushHeaders();
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);
    }

    #[Test]
    public function nothing_is_shown_before_publication_then_only_what_was_published(): void
    {
        $s = $this->officialScene();
        $student = $this->portalUser($s['school'], $this->studentOf($s['final']));
        $this->get('/portal/results')->assertOk()->assertInertia(fn (Assert $page) => $page->component('portal/results')->has('rows', 0));

        $this->publish($s);
        $this->actAs($student, $s['school']);
        $this->get('/portal/results')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.enrollment_id', $s['final'])
            ->where('rows.0.annual.incomplete', false)
            ->where('rows.0.gpa', null));

        $this->publish($s, 'gpa');
        $this->actAs($student, $s['school']);
        $this->get('/portal/results')->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.gpa.incomplete', false));
    }

    #[Test]
    public function a_withdrawn_publication_or_a_newer_unpublished_version_disappears(): void
    {
        $s = $this->officialScene();
        $this->publish($s);
        $student = $this->portalUser($s['school'], $this->studentOf($s['final']));
        $this->get('/portal/results')->assertInertia(fn (Assert $page) => $page->has('rows', 1));

        DB::table(SchemaHelper::qualified('results', 'annual_results'))->where('enrollment_id', $s['final'])->where('is_current_official', true)
            ->update(['result_version' => DB::raw('result_version + 10')]);
        $this->get('/portal/results')->assertInertia(fn (Assert $page) => $page->has('rows', 0));

        DB::table(SchemaHelper::qualified('results', 'annual_results'))->where('enrollment_id', $s['final'])->where('is_current_official', true)
            ->update(['result_version' => DB::raw('result_version - 10')]);
        $this->get('/portal/results')->assertInertia(fn (Assert $page) => $page->has('rows', 1));
        $id = (int) DB::table(SchemaHelper::qualified('results', 'result_publications'))->value('id');
        $this->flushHeaders();
        $this->as('ResultsPublisher', $s['school']);
        $this->from('/results/publications')->post("/results/publications/{$id}/withdraw", ['reason' => 'سحب للاختبار'], ['X-Idempotency-Key' => 'u02-w'])->assertSessionHas('success');

        $this->actAs($student, $s['school']);
        $this->get('/portal/results')->assertInertia(fn (Assert $page) => $page->has('rows', 0));
    }

    #[Test]
    public function only_the_users_own_student_is_shown_and_no_parameter_changes_it(): void
    {
        $s = $this->officialScene();
        $this->publish($s);
        // Linked to a student of the same class whose result is NOT published (another section, no official result).
        $this->portalUser($s['school'], $this->studentOf($s['entered']));

        $query = http_build_query(['student_id' => $this->studentOf($s['final']), 'enrollment_id' => $s['final'], 'academic_year_id' => $s['year_id']]);
        $this->get('/portal/results?'.$query)->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows', 0));
        $this->get('/portal/results')->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows', 0));
    }

    #[Test]
    public function a_guardian_sees_the_linked_student(): void
    {
        $s = $this->officialScene();
        $this->publish($s);
        $this->portalUser($s['school'], $this->studentOf($s['final']), guardian: true);

        $this->get('/portal/results')->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.enrollment_id', $s['final']));
    }

    #[Test]
    public function another_school_context_shows_nothing_of_this_school(): void
    {
        $s = $this->officialScene();
        $this->publish($s);
        $other = $this->createSchool('SCH-U02-OTHER', 'Other school');
        // A portal user of another school holding a scope on this school's student id.
        $this->portalUser($other, $this->studentOf($s['final']));

        $this->get('/portal/results')->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows', 0));
    }

    #[Test]
    public function staff_are_refused_and_the_sidebar_entry_is_only_for_portal_users(): void
    {
        $s = $this->officialScene();
        $this->flushHeaders();
        $this->as('ResultsViewer', $s['school']);
        $this->get('/portal/results')->assertForbidden();
        $this->get('/results/publications?academic_year_id='.$s['year_id'])->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('portalNav.results', false));

        $this->portalUser($s['school'], $this->studentOf($s['final']));
        $this->get('/portal/results')->assertOk()->assertInertia(fn (Assert $page) => $page->where('portalNav.results', true));
    }
}
