<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseUiResTermResultsPagePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function guest_is_redirected_from_results_list(): void
    {
        $this->get('/results')->assertRedirect('/login');
    }

    #[Test]
    public function results_viewer_can_view_results_list(): void
    {
        $schoolId = $this->createSchool('SCH-RES-UI', 'Results UI');
        $this->createAcademicYear();
        $this->actingAsResultsViewerForSchool($schoolId);

        $this->get('/results')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('results/index')
                ->has('results.data')
                ->has('filters'));
    }
}
