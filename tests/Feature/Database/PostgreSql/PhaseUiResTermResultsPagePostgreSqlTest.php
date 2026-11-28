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
                // The «النتائج» board (2026-11-28): layout only — no results data on the page.
                ->component('results/index')
                ->missing('results')
                ->missing('filters'));
    }

    #[Test]
    public function results_viewer_opens_the_entry_window_page_with_the_school_structure_only(): void
    {
        $this->get('/results/entry-window')->assertRedirect('/login');

        $schoolId = $this->createSchool('SCH-RES-EW', 'Results entry window');
        $this->createAcademicYear();
        $this->actingAsResultsViewerForSchool($schoolId);

        // «إدخال الدرجات / إعلان النتائج للطلبة» (2026-11-28): the school's الفرع · الاختصاص · الصف · الشعبة only —
        // no grades, students or results are sent.
        $this->get('/results/entry-window')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('results/entry-window')
                ->has('structure.branches')
                ->has('structure.departments')
                ->has('structure.classes')
                ->has('structure.sections')
                ->has('filters.academic_year_id')
                ->missing('results')
                ->missing('students'));
    }
}
