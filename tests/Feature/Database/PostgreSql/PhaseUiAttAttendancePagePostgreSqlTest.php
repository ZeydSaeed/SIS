<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseUiAttAttendancePagePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function guest_is_redirected_from_attendance_list(): void
    {
        $this->get('/attendance')->assertRedirect('/login');
    }

    #[Test]
    public function attendance_manager_can_view_attendance_list(): void
    {
        $schoolId = $this->createSchool('SCH-ATT-UI', 'Attendance UI');
        $this->createAcademicYear();
        $this->actingAsAttendanceManagerForSchool($schoolId);

        $this->get('/attendance')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('attendance/index')
                ->has('sessions.data')
                ->has('filters'));
    }
}
