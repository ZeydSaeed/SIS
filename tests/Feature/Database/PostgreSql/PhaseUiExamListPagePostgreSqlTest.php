<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseUiExamListPagePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function guest_is_redirected_from_exams_list(): void
    {
        $this->get('/exams')->assertRedirect('/login');
    }

    #[Test]
    public function grades_manager_can_view_exams_list(): void
    {
        $schoolId = $this->createSchool('SCH-EXAM-UI', 'Exam UI');
        $this->createAcademicYear();
        $this->actingAsGradesManagerForSchool($schoolId);

        $this->get('/exams')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('exams/index')
                ->has('exams.data')
                ->has('filters'));
    }
}
