<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseUiEnrEnrollmentsPagePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function guest_is_redirected_from_enrollment_list(): void
    {
        $this->get('/enrollments')->assertRedirect('/login');
    }

    #[Test]
    public function enrollment_manager_can_view_enrollment_list(): void
    {
        $schoolId = $this->createSchool('SCH-ENR-UI', 'Enrollment UI');
        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->get('/enrollments')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('enrollments/index')
                ->has('enrollments.data')
                ->has('filters'));
    }
}
