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
                ->has('teachers')
                ->has('subjects')
                ->has('filters')
                ->where('authorization.can_manage', false));
    }

    #[Test]
    public function teachers_viewer_cannot_register_a_teacher(): void
    {
        $schoolId = $this->createSchool('SCH-TCH-UI-RO', 'Teachers UI read-only');
        $yearId = $this->createAcademicYear();
        $this->actingAsTeachersViewerForSchool($schoolId);

        $this->withHeader('X-Idempotency-Key', 'ui-teach-ro-1')
            ->post('/teachers', [
                'academic_year_id' => $yearId,
                'employee_code' => 'EMP-RO-1',
                'first_name' => 'Ali',
                'last_name' => 'Hassan',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function teachers_manager_registers_edits_and_deactivates_a_teacher_from_the_page(): void
    {
        $schoolId = $this->createSchool('SCH-TCH-UI-W', 'Teachers UI write');
        $yearId = $this->createAcademicYear();
        $this->actingAsTeachersManagerForSchool($schoolId);

        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'ui-teach-w-1')
            ->post('/teachers', [
                'academic_year_id' => $yearId,
                'employee_code' => 'EMP-UI-1',
                'first_name' => 'Ali',
                'last_name' => 'Hassan',
                'specialization_field' => 'Mathematics',
            ])
            ->assertRedirect('/teachers')
            ->assertSessionHas('success', 'flash.teachers.registered');

        $teacherId = 0;
        $this->get('/teachers?academic_year_id='.$yearId)
            ->assertSuccessful()
            ->assertInertia(function ($page) use (&$teacherId) {
                $page->component('teachers/index')
                    ->where('authorization.can_manage', true)
                    ->has('teachers', 1)
                    ->where('teachers.0.employee_code', 'EMP-UI-1')
                    ->where('teachers.0.status', 1)
                    ->etc();
                $teacherId = (int) $page->toArray()['props']['teachers'][0]['id'];
            });

        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'ui-teach-w-2')
            ->patch('/teachers/'.$teacherId, [
                'first_name' => 'Ali',
                'last_name' => 'Kareem',
                'specialization_field' => 'Physics',
            ])
            ->assertRedirect('/teachers')
            ->assertSessionHas('success', 'flash.teachers.updated');

        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'ui-teach-w-3')
            ->post('/teachers/'.$teacherId.'/deactivate')
            ->assertRedirect('/teachers')
            ->assertSessionHas('success', 'flash.teachers.deactivated');

        $this->get('/teachers?academic_year_id='.$yearId)
            ->assertInertia(fn ($page) => $page
                ->where('teachers.0.last_name', 'Kareem')
                ->where('teachers.0.specialization_field', 'Physics')
                ->where('teachers.0.status', 2)
                ->etc());
    }

    #[Test]
    public function duplicate_employee_code_returns_the_domain_error_code(): void
    {
        $schoolId = $this->createSchool('SCH-TCH-UI-D', 'Teachers UI duplicate');
        $yearId = $this->createAcademicYear();
        $this->actingAsTeachersManagerForSchool($schoolId);

        $payload = [
            'academic_year_id' => $yearId,
            'employee_code' => 'EMP-DUP-1',
            'first_name' => 'Ali',
            'last_name' => 'Hassan',
        ];
        $this->from('/teachers')->withHeader('X-Idempotency-Key', 'ui-teach-d-1')->post('/teachers', $payload);

        $this->from('/teachers')
            ->withHeader('X-Idempotency-Key', 'ui-teach-d-2')
            ->post('/teachers', $payload)
            ->assertRedirect('/teachers')
            ->assertSessionHasErrors(['teacher' => 'teachers.employee_code_taken']);
    }
}
