<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseUiEnrEnrollmentFormsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function guest_is_redirected_from_enrollment_create(): void
    {
        $this->get('/enrollments/create')->assertRedirect('/login');
    }

    #[Test]
    public function enrollment_manager_can_view_create_form(): void
    {
        $schoolId = $this->createSchool('SCH-ENR-CREATE', 'Enrollment Create UI');
        $this->createAcademicYear();
        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->get('/enrollments/create')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('enrollments/create')
                ->has('defaults'));
    }
}
