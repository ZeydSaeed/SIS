<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * EXAMIS R-09 — a portal-only account (student / guardian) is told apart on the server (`portalNav.portalOnly`) so the
 * sidebar and the dashboard show it «نتائجي» instead of the staff modules; staff accounts are unchanged, and the staff
 * pages still refuse a portal-only account on the server.
 */
final class PortalNavigationPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    private function user(int $schoolId, string ...$grants): User
    {
        $user = User::factory()->create();
        foreach ($grants as $grant) {
            app(SecurityPermissionSeeder::class)->{'grant'.$grant}($user, $schoolId);
        }
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $schoolId]);

        return $user;
    }

    #[Test]
    public function a_portal_only_account_is_flagged_and_still_refused_by_staff_pages(): void
    {
        $school = $this->createSchool('SCH-R09-A', 'R-09 A');
        $this->user($school, 'PortalResultsViewer');

        $this->get('/portal/results')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('portalNav.results', true)
            ->where('portalNav.portalOnly', true));
        $this->get('/results/publications')->assertForbidden();
        $this->get('/results/term-publications')->assertForbidden();
    }

    #[Test]
    public function a_portal_account_that_also_holds_a_staff_role_keeps_the_staff_modules(): void
    {
        $school = $this->createSchool('SCH-R09-B', 'R-09 B');
        $this->user($school, 'PortalResultsViewer', 'ResultsViewer');

        $this->get('/portal/results')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('portalNav.results', true)
            ->where('portalNav.portalOnly', false));
    }

    #[Test]
    public function a_staff_account_is_not_portal_only(): void
    {
        $school = $this->createSchool('SCH-R09-C', 'R-09 C');
        $this->user($school, 'ResultsViewer');

        $this->get('/results/publications')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('portalNav.results', false)
            ->where('portalNav.portalOnly', false));
    }
}
