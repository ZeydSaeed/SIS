<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseUiResResultsDetailPagePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function guest_is_redirected_from_results_show(): void
    {
        $this->get('/results/show')->assertRedirect('/login');
    }

    #[Test]
    public function results_viewer_can_view_results_summary(): void
    {
        $schoolId = $this->createSchool('SCH-RES-UI2', 'Results UI Detail');
        $this->createAcademicYear();
        $this->actingAsResultsViewerForSchool($schoolId);

        $this->get('/results/show')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('results/show')
                ->has('terms')
                ->has('filters'));
    }

    #[Test]
    public function results_viewer_can_view_term_detail_page(): void
    {
        $schoolId = $this->createSchool('SCH-RES-UI2B', 'Results UI Term');
        $this->createAcademicYear();
        $this->actingAsResultsViewerForSchool($schoolId);

        $this->get('/results/term')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('results/term')
                ->has('filters'));
    }
}
