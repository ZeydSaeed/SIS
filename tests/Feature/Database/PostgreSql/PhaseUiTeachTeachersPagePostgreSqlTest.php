<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseUiTeachTeachersPagePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function guest_is_redirected_from_teachers_list(): void
    {
        $this->get('/teachers')->assertRedirect('/login');
    }

    #[Test]
    public function teachers_viewer_can_view_teachers_list(): void
    {
        $schoolId = $this->createSchool('SCH-TCH-UI', 'Teachers UI');
        $this->createAcademicYear();
        $this->actingAsTeachersViewerForSchool($schoolId);

        $this->get('/teachers')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('teachers/index')
                ->has('teachers.data')
                ->has('filters'));
    }
}
