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

    #[Test]
    public function personal_lesson_limits_are_saved_exposed_kept_and_validated(): void
    {
        $schoolId = $this->createSchool('SCH-TCH-UI-L', 'Teachers UI limits');
        $yearId = $this->createAcademicYear();
        $this->actingAsTeachersManagerForSchool($schoolId);

        $this->from('/teachers')->withHeader('X-Idempotency-Key', 'ui-teach-l-1')->post('/teachers', [
            'academic_year_id' => $yearId, 'employee_code' => 'EMP-L-1', 'first_name' => 'Ali', 'last_name' => 'Hassan',
        ]);
        $teacherId = (int) \DB::table('teachers.teachers')->where('employee_code', 'EMP-L-1')->value('id');
        $base = ['first_name' => 'Ali', 'last_name' => 'Hassan', 'academic_year_id' => $yearId];

        // Saved and exposed to the page.
        $this->from('/teachers')->withHeader('X-Idempotency-Key', 'ui-teach-l-2')
            ->patch('/teachers/'.$teacherId, $base + ['weekly_lessons_min' => 10, 'weekly_lessons_max' => 20, 'daily_lessons_max' => 5])
            ->assertSessionHas('success', 'flash.teachers.updated');
        $this->get('/teachers?academic_year_id='.$yearId)->assertInertia(fn ($page) => $page
            ->where('teachers.0.weekly_lessons_min', 10)
            ->where('teachers.0.weekly_lessons_max', 20)
            ->where('teachers.0.daily_lessons_max', 5)
            ->etc());

        // An update that does not mention the limits keeps them.
        $this->from('/teachers')->withHeader('X-Idempotency-Key', 'ui-teach-l-3')
            ->patch('/teachers/'.$teacherId, $base + ['specialization_field' => 'Physics']);
        $this->get('/teachers?academic_year_id='.$yearId)->assertInertia(fn ($page) => $page
            ->where('teachers.0.weekly_lessons_max', 20)->etc());

        // Incoherent limits are refused with a domain code and change nothing.
        $this->from('/teachers')->withHeader('X-Idempotency-Key', 'ui-teach-l-4')
            ->patch('/teachers/'.$teacherId, $base + ['weekly_lessons_min' => 15, 'weekly_lessons_max' => 10, 'daily_lessons_max' => null])
            ->assertSessionHasErrors(['teacher' => 'teachers.workload_min_above_max']);
        $this->from('/teachers')->withHeader('X-Idempotency-Key', 'ui-teach-l-5')
            ->patch('/teachers/'.$teacherId, $base + ['weekly_lessons_min' => null, 'weekly_lessons_max' => 4, 'daily_lessons_max' => 6])
            ->assertSessionHasErrors(['teacher' => 'teachers.workload_daily_above_weekly']);
        $this->get('/teachers?academic_year_id='.$yearId)->assertInertia(fn ($page) => $page
            ->where('teachers.0.weekly_lessons_max', 20)->where('teachers.0.daily_lessons_max', 5)->etc());

        // Empty values clear the limits.
        $this->from('/teachers')->withHeader('X-Idempotency-Key', 'ui-teach-l-6')
            ->patch('/teachers/'.$teacherId, $base + ['weekly_lessons_min' => null, 'weekly_lessons_max' => null, 'daily_lessons_max' => null]);
        $this->get('/teachers?academic_year_id='.$yearId)->assertInertia(fn ($page) => $page
            ->where('teachers.0.weekly_lessons_max', null)->where('teachers.0.daily_lessons_max', null)->etc());
    }

    #[Test]
    public function deactivating_a_teacher_with_lessons_warns_how_many_stay_on_the_grid(): void
    {
        $schoolId = $this->createSchool('SCH-TCH-UI-LS', 'Teachers UI lessons');
        $yearId = $this->createAcademicYear();
        $this->actingAsTeachersManagerForSchool($schoolId);

        $this->from('/teachers')->withHeader('X-Idempotency-Key', 'ui-teach-ls-1')->post('/teachers', [
            'academic_year_id' => $yearId, 'employee_code' => 'EMP-LS-1', 'first_name' => 'Ali', 'last_name' => 'Hassan',
        ]);
        $teacherId = (int) \DB::table('teachers.teachers')->where('employee_code', 'EMP-LS-1')->value('id');
        $class = $this->createClassForSchool($schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);
        $subjectId = (int) \DB::table('curriculum.subjects')->insertGetId([
            'code' => 'SUB-LS', 'name' => 'Subject LS', 'subject_type' => 1, 'max_grade' => 100, 'pass_grade' => 50, 'status' => 1,
        ]);
        \DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $schoolId]);
        foreach ([1, 2] as $n) {
            $periodId = (int) \DB::table('timetable.periods')->insertGetId([
                'school_id' => $schoolId, 'period_number' => $n, 'start_time' => sprintf('0%d:00', 7 + $n), 'end_time' => sprintf('0%d:45', 7 + $n), 'period_type' => 1,
            ]);
            \DB::table('timetable.schedules')->insert([
                'school_id' => $schoolId, 'section_id' => $section->id, 'academic_year_id' => $yearId, 'day_of_week' => 1,
                'period_id' => $periodId, 'subject_id' => $subjectId, 'teacher_id' => $teacherId, 'lifecycle_status' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->from('/teachers')->withHeader('X-Idempotency-Key', 'ui-teach-ls-2')
            ->post('/teachers/'.$teacherId.'/deactivate')
            ->assertSessionHas('success', 'flash.teachers.deactivated')
            ->assertSessionHas('toast', ['type' => 'warning', 'message' => 'teachers.deactivated_with_lessons?count=2']);
    }
}
