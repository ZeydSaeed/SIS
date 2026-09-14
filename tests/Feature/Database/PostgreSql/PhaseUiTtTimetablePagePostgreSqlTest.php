<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseUiTtTimetablePagePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function guest_is_redirected_from_timetable_list(): void
    {
        $this->get('/timetable')->assertRedirect('/login');
    }

    #[Test]
    public function timetable_manager_can_view_timetable_list(): void
    {
        $schoolId = $this->createSchool('SCH-TT-UI', 'Timetable UI');
        $this->createAcademicYear();
        $this->actingAsTimetableManagerForSchool($schoolId);

        $this->get('/timetable')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('timetable/index')
                ->has('schedules.data')
                ->has('filters'));
    }
}
