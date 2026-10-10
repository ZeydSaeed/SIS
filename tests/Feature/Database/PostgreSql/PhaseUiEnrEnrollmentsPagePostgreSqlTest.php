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

    #[Test]
    public function list_filters_accept_several_ids_and_a_single_legacy_id(): void
    {
        $schoolId = $this->createSchool('SCH-ENR-MULTI', 'Enrollment multi filter');
        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->get('/enrollments?class_id=3,5&section_id=7,8&branch_id=2,4,2&department_id=9')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('enrollments/index')
                ->where('filters.class_id', [3, 5])
                ->where('filters.section_id', [7, 8])
                ->where('filters.branch_id', [2, 4])
                ->where('filters.department_id', [9]));

        $this->get('/enrollments?class_id=abc,-1,0')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('filters.class_id', []));
    }
}
