<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseUiTranscriptPagePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function guest_is_redirected_from_transcript_page(): void
    {
        $this->get('/results/transcript')->assertRedirect('/login');
    }

    #[Test]
    public function results_viewer_can_view_transcript_page(): void
    {
        $schoolId = $this->createSchool('SCH-TRN-UI', 'Transcript UI');
        $this->createAcademicYear();
        $this->actingAsResultsViewerForSchool($schoolId);

        $this->get('/results/transcript')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('results/transcript')
                ->has('filters'));
    }
}
