<?php

namespace Tests\Feature\Enrollment;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\TestCase;

final class EnrollmentUiTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;

    #[Test]
    public function guest_is_redirected_from_enrollment_list(): void
    {
        $this->get('/enrollments')->assertRedirect('/login');
    }

    #[Test]
    public function enrollment_manager_can_view_enrollment_list_with_related_fields(): void
    {
        $this->withoutVite();
        $this->actingAsEnrollmentManagerWeb();
        $schoolId = (int) session('current_school_id');
        $yearId = $this->createAcademicYear('AY-ENR-UI');
        $student = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-UI-001',
            'first_name' => 'Hassan',
            'father_name' => 'Ali',
            'last_name' => 'Kadhim',
            'full_name' => 'Hassan Ali Kadhim',
            'gender' => 1,
            'birth_date' => '2012-03-15',
            'department_name' => 'علمي',
        ]);
        $this->createActiveEnrollmentForSchool($schoolId, $yearId, $student);

        $this->get("/enrollments?academic_year_id={$yearId}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('enrollments/index')
                ->has('enrollments.data', 1)
                ->has('enrollments.status_progress')
                ->where('enrollments.status_progress.overall_percent', 100)
                ->where('enrollments.data.0.student_code', 'STU-ENR-UI-001')
                ->where('enrollments.data.0.student_first_name', 'Hassan')
                ->where('enrollments.data.0.class_name', 'Test Class')
                ->where('enrollments.data.0.section_name', 'Test Section')
                ->where('enrollments.data.0.academic_year_name', 'Academic Year AY-ENR-UI')
                ->where('enrollments.data.0.department_name', 'علمي')
                ->where('enrollments.data.0.student_gender', 1)
                ->has('filters')
                ->where('filters.q', '')
                ->has('filterOptions.branches')
                ->has('filterOptions.classes')
                ->has('filterOptions.sections')
                ->has('filterOptions.departments')
                ->has('filterOptions.specializations')
                ->where('enrollments.status_progress.stages.0.status', null)
                ->where('enrollments.status_progress.stages.1.status', 1)
                ->where('enrollments.status_progress.stages.2.status', 0)
                ->where('enrollments.status_progress.stages.3.status', 2)
                ->where('enrollments.status_progress.stages.4.status', 3)
                ->where('authorization.canView', true)
                ->where('authorization.canCreate', true)
                ->where('authorization.canCancel', true));
    }

    #[Test]
    public function enrollment_list_filters_by_gender(): void
    {
        $this->withoutVite();
        $this->actingAsEnrollmentManagerWeb();
        $schoolId = (int) session('current_school_id');
        $yearId = $this->createAcademicYear('AY-ENR-GENDER');

        $male = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-M',
            'first_name' => 'MaleEnr',
            'last_name' => 'Student',
            'full_name' => 'MaleEnr Student',
            'gender' => 1,
        ]);
        $female = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-F',
            'first_name' => 'FemaleEnr',
            'last_name' => 'Student',
            'full_name' => 'FemaleEnr Student',
            'gender' => 2,
        ]);
        $this->createActiveEnrollmentForSchool($schoolId, $yearId, $male);
        $this->createActiveEnrollmentForSchool($schoolId, $yearId, $female);

        $this->get("/enrollments?academic_year_id={$yearId}&gender=2")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('filters.gender', 2)
                ->has('enrollments.data', 1)
                ->where('enrollments.data.0.student_code', 'STU-ENR-F')
                ->where('enrollments.status_progress.stages.0.count', 1));
    }

    #[Test]
    public function enrollment_list_filters_by_status(): void
    {
        $this->withoutVite();
        $this->actingAsEnrollmentManagerWeb();
        $schoolId = (int) session('current_school_id');
        $yearId = $this->createAcademicYear('AY-ENR-STATUS');

        $activeStudent = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-ACTIVE',
            'first_name' => 'ActiveEnr',
            'last_name' => 'Student',
            'full_name' => 'ActiveEnr Student',
        ]);
        $cancelledStudent = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-CANCELLED',
            'first_name' => 'CancelledEnr',
            'last_name' => 'Student',
            'full_name' => 'CancelledEnr Student',
        ]);
        $this->createActiveEnrollmentForSchool($schoolId, $yearId, $activeStudent);
        $cancelled = $this->createActiveEnrollmentForSchool($schoolId, $yearId, $cancelledStudent);
        $cancelled->forceFill([
            'status' => 2,
            'effective_to' => '2026-10-01',
        ])->save();

        $this->get("/enrollments?academic_year_id={$yearId}&status=2")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('filters.status', 2)
                ->has('enrollments.data', 1)
                ->where('enrollments.data.0.student_code', 'STU-ENR-CANCELLED')
                ->where('enrollments.status_progress.stages.0.count', 2)
                ->where('enrollments.status_progress.stages.1.count', 1)
                ->where('enrollments.status_progress.stages.2.count', 0)
                ->where('enrollments.status_progress.stages.3.count', 1)
                ->where('enrollments.status_progress.stages.4.count', 0));
    }

    #[Test]
    public function enrollment_list_search_matches_student_and_keeps_gender_filter(): void
    {
        $this->withoutVite();
        $this->actingAsEnrollmentManagerWeb();
        $schoolId = (int) session('current_school_id');
        $yearId = $this->createAcademicYear('AY-ENR-SEARCH');

        $male = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-SEARCH-M',
            'first_name' => 'SearchEnr',
            'last_name' => 'Male',
            'full_name' => 'SearchEnr Male',
            'gender' => 1,
        ]);
        $female = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-SEARCH-F',
            'first_name' => 'SearchEnr',
            'last_name' => 'Female',
            'full_name' => 'SearchEnr Female',
            'gender' => 2,
        ]);
        $this->createActiveEnrollmentForSchool($schoolId, $yearId, $male);
        $this->createActiveEnrollmentForSchool($schoolId, $yearId, $female);

        $this->get("/enrollments?academic_year_id={$yearId}&q=SearchEnr&gender=2")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('filters.q', 'SearchEnr')
                ->where('filters.gender', 2)
                ->has('enrollments.data', 1)
                ->where('enrollments.data.0.student_code', 'STU-ENR-SEARCH-F'));
    }

    #[Test]
    public function enrollment_show_includes_related_names(): void
    {
        $this->withoutVite();
        $this->actingAsEnrollmentManagerWeb();
        $schoolId = (int) session('current_school_id');
        $yearId = $this->createAcademicYear('AY-ENR-SHOW');
        $student = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-SHOW',
            'first_name' => 'ShowEnr',
            'last_name' => 'Student',
            'full_name' => 'ShowEnr Student',
        ]);
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId, $student);

        $this->get("/enrollments/{$enrollment->id}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('enrollments/show')
                ->where('enrollment.student_code', 'STU-ENR-SHOW')
                ->where('enrollment.class_name', 'Test Class')
                ->where('enrollment.section_name', 'Test Section'));
    }

    #[Test]
    public function enrollment_api_list_includes_related_fields(): void
    {
        $schoolId = $this->createSchool('SCHOOL-ENR-API', 'School ENR API');
        $yearId = $this->createAcademicYear('AY-ENR-API');
        $student = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-API',
            'first_name' => 'ApiEnr',
            'last_name' => 'Student',
            'full_name' => 'ApiEnr Student',
        ]);
        $this->createActiveEnrollmentForSchool($schoolId, $yearId, $student);
        $this->actingAsEnrollmentManager(schoolId: $schoolId);

        $this->getJson("/api/v1/enrollments?academic_year_id={$yearId}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.student_code', 'STU-ENR-API')
            ->assertJsonPath('data.0.class_name', 'Test Class')
            ->assertJsonPath('data.0.section_name', 'Test Section');
    }
}
