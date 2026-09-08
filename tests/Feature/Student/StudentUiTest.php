<?php

namespace Tests\Feature\Student;

use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\TestCase;

final class StudentUiTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;

    #[Test]
    public function guest_is_redirected_from_student_list(): void
    {
        $this->get('/students')->assertRedirect('/login');
    }

    #[Test]
    public function authenticated_user_without_school_context_is_forbidden_on_student_list(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantStudentManager($user, $this->createSchool('SCHOOL-A', 'School A'));

        $this->actingAs($user)
            ->get('/students')
            ->assertForbidden();
    }

    #[Test]
    public function student_manager_can_view_student_list(): void
    {
        $this->withoutVite();
        $this->actingAsStudentManagerWeb();
        $schoolId = (int) session('current_school_id');

        $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-WEB-001',
            'first_name' => 'Sara',
            'last_name' => 'Ali',
            'full_name' => 'Sara Ali',
        ]);

        $this->get('/students')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('students/index')
                ->has('students.data')
                ->has('authorization')
                ->where('authorization.canView', true));
    }

    #[Test]
    public function student_list_search_returns_filtered_results(): void
    {
        $this->withoutVite();
        $user = $this->actingAsStudentManagerWeb();
        $schoolId = (int) session('current_school_id');

        $this->createStudentForSchool($schoolId, [
            'first_name' => 'Unique',
            'last_name' => 'Student',
            'full_name' => 'Unique Student',
            'student_code' => 'STU-SEARCH-001',
        ]);

        $this->actingAs($user)
            ->withSession(['current_school_id' => $schoolId])
            ->get('/students?q=Unique')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('students/index')
                ->where('filters.q', 'Unique')
                ->has('students.data', 1));
    }

    #[Test]
    public function student_list_pagination_is_reflected_in_props(): void
    {
        $this->withoutVite();
        $this->actingAsStudentManagerWeb();
        $schoolId = (int) session('current_school_id');

        for ($i = 0; $i < 3; $i++) {
            $this->createStudentForSchool($schoolId, [
                'student_code' => 'STU-PAGE-'.$i,
                'first_name' => 'Page',
                'last_name' => 'Student '.$i,
                'full_name' => 'Page Student '.$i,
            ]);
        }

        $this->get('/students?page=1&per_page=2')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('filters.page', 1)
                ->where('filters.per_page', 2)
                ->has('students.data', 2));
    }

    #[Test]
    public function student_viewer_without_update_permission_has_can_update_false_on_list(): void
    {
        $this->withoutVite();
        $this->actingAsStudentViewerWeb();

        $this->get('/students')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('authorization.canUpdate', false));
    }

    #[Test]
    public function authorized_user_can_view_student_details_page(): void
    {
        $this->withoutVite();
        $this->actingAsStudentManagerWeb();
        $schoolId = (int) session('current_school_id');

        $student = $this->createStudentForSchool($schoolId, [
            'first_name' => 'Detail',
            'last_name' => 'Student',
            'full_name' => 'Detail Student',
            'student_code' => 'STU-DETAIL-001',
            'national_id' => 'NAT-DETAIL-001',
        ]);

        $this->get("/students/{$student->id}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('students/show')
                ->has('student')
                ->where('student.full_name', 'Detail Student')
                ->where('authorization.canView', true));
    }

    #[Test]
    public function missing_student_details_returns_forbidden(): void
    {
        $this->withoutVite();
        $this->actingAsStudentManagerWeb();

        $this->get('/students/999999')
            ->assertForbidden();
    }

    #[Test]
    public function cross_school_student_details_are_forbidden(): void
    {
        $this->withoutVite();

        $schoolA = $this->createSchool('SCHOOL-A', 'School A');
        $schoolB = $this->createSchool('SCHOOL-B', 'School B');

        $student = $this->createStudentForSchool($schoolB, [
            'student_code' => 'STU-OTHER-001',
            'first_name' => 'Other',
            'last_name' => 'School',
            'full_name' => 'Other School',
        ]);

        $user = $this->actingAsStudentManagerWeb(null, $schoolA);

        $this->actingAs($user)
            ->withSession(['current_school_id' => $schoolA])
            ->get("/students/{$student->id}")
            ->assertForbidden();
    }

    #[Test]
    public function viewer_without_pii_permission_does_not_receive_national_id_on_show(): void
    {
        $this->withoutVite();
        $this->actingAsStudentViewerWeb();
        $schoolId = (int) session('current_school_id');

        $student = $this->createStudentForSchool($schoolId, [
            'first_name' => 'No',
            'last_name' => 'Pii',
            'full_name' => 'No Pii',
            'student_code' => 'STU-NOPII-001',
            'national_id' => 'NAT-SECRET-001',
        ]);

        $this->get("/students/{$student->id}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->missing('student.national_id')
                ->where('authorization.canViewPii', false));
    }

    #[Test]
    public function preview_query_returns_student_payload_for_authorized_user(): void
    {
        $this->withoutVite();
        $this->actingAsStudentManagerWeb();
        $schoolId = (int) session('current_school_id');

        $student = $this->createStudentForSchool($schoolId, [
            'first_name' => 'Preview',
            'last_name' => 'Student',
            'full_name' => 'Preview Student',
            'student_code' => 'STU-PREV-001',
        ]);

        $this->get("/students?student={$student->id}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->has('preview.student')
                ->where('preview.student.full_name', 'Preview Student'));
    }

    #[Test]
    public function preview_query_returns_forbidden_for_cross_school_student(): void
    {
        $this->withoutVite();

        $schoolA = $this->createSchool('SCHOOL-A', 'School A');
        $schoolB = $this->createSchool('SCHOOL-B', 'School B');

        $student = $this->createStudentForSchool($schoolB, [
            'student_code' => 'STU-CROSS-001',
            'first_name' => 'Cross',
            'last_name' => 'School',
            'full_name' => 'Cross School',
        ]);

        $user = $this->actingAsStudentManagerWeb(null, $schoolA);

        $this->actingAs($user)
            ->withSession(['current_school_id' => $schoolA])
            ->get("/students?student={$student->id}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('preview.error', 'forbidden'));
    }
}
