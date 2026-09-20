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
    public function authenticated_user_with_invalid_session_school_id_is_forbidden_on_student_list(): void
    {
        $this->withoutVite();

        $schoolA = $this->createSchool('SCHOOL-A', 'School A');
        $schoolB = $this->createSchool('SCHOOL-B', 'School B');

        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantStudentManager($user, $schoolA);

        $this->actingAs($user)
            ->withSession(['current_school_id' => $schoolB])
            ->get('/students')
            ->assertForbidden()
            ->assertDontSee('student_code', false);
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
                ->has('students.status_progress')
                ->where('students.status_progress.overall_percent', 100)
                ->where('students.status_progress.stages.0.percent', 100)
                ->where('students.status_progress.stages.1.status', 1)
                ->where('students.status_progress.stages.1.percent', 100)
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

    #[Test]
    public function student_list_status_filter_is_reflected_in_props(): void
    {
        $this->withoutVite();
        $this->actingAsStudentManagerWeb();
        $schoolId = (int) session('current_school_id');

        $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ACTIVE-001',
            'first_name' => 'Active',
            'last_name' => 'One',
            'full_name' => 'Active One',
            'status' => 1,
        ]);
        $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-INACTIVE-001',
            'first_name' => 'Inactive',
            'last_name' => 'One',
            'full_name' => 'Inactive One',
            'status' => 0,
        ]);

        $this->get('/students?status=0')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('filters.status', 0)
                ->has('students.data', 1)
                ->where('students.data.0.full_name', 'Inactive One'));
    }

    #[Test]
    public function student_list_search_matches_grandfather_name_and_keeps_status_filter(): void
    {
        $this->withoutVite();
        $this->actingAsStudentManagerWeb();
        $schoolId = (int) session('current_school_id');

        $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-GF-001',
            'first_name' => 'Ahmad',
            'father_name' => 'Ali',
            'grandfather_name' => 'UniqueGrand',
            'last_name' => 'Hassan',
            'full_name' => 'Ahmad Hassan',
            'status' => 1,
        ]);
        $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-GF-002',
            'first_name' => 'Omar',
            'grandfather_name' => 'UniqueGrand',
            'last_name' => 'Said',
            'full_name' => 'Omar Said',
            'status' => 0,
        ]);

        $this->get('/students?q=UniqueGrand&status=1')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('filters.q', 'UniqueGrand')
                ->where('filters.status', 1)
                ->has('students.data', 1)
                ->where('students.data.0.student_code', 'STU-GF-001'));
    }

    #[Test]
    public function student_list_includes_civil_profile_columns_from_admission(): void
    {
        $this->withoutVite();
        $this->actingAsStudentManagerWeb();
        $schoolId = (int) session('current_school_id');

        $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-CIVIL-001',
            'first_name' => 'ليث',
            'father_name' => 'سامر',
            'grandfather_name' => 'كامل',
            'great_grandfather_name' => 'جواد',
            'last_name' => 'العبيدي',
            'mother_name' => 'هدى',
            'maternal_father_name' => 'كريم',
            'maternal_grandfather_name' => 'ناظم',
            'full_name' => 'ليث سامر كامل جواد العبيدي',
            'birth_date' => '2011-07-04',
            'birth_place' => 'البصرة',
            'gender' => 1,
            'national_id' => 'CIVIL-001',
            'governorate' => 'البصرة',
            'neighborhood' => 'العشار',
            'school_name' => 'Demo Vocational School',
            'admitted_class_name' => 'الثالث',
            'department_name' => 'صناعي',
            'specialization_name' => 'ميكانيك',
        ]);

        $this->get('/students')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('students/index')
                ->where('students.data.0.first_name', 'ليث')
                ->where('students.data.0.father_name', 'سامر')
                ->where('students.data.0.grandfather_name', 'كامل')
                ->where('students.data.0.great_grandfather_name', 'جواد')
                ->where('students.data.0.last_name', 'العبيدي')
                ->where('students.data.0.mother_name', 'هدى')
                ->where('students.data.0.maternal_father_name', 'كريم')
                ->where('students.data.0.maternal_grandfather_name', 'ناظم')
                ->where('students.data.0.birth_date', '2011-07-04')
                ->where('students.data.0.birth_place', 'البصرة')
                ->where('students.data.0.gender', 1)
                ->where('students.data.0.national_id', 'CIVIL-001')
                ->where('students.data.0.governorate', 'البصرة')
                ->where('students.data.0.neighborhood', 'العشار')
                ->where('students.data.0.school_name', 'Demo Vocational School')
                ->where('students.data.0.admitted_class_name', 'الثالث')
                ->where('students.data.0.department_name', 'صناعي')
                ->where('students.data.0.specialization_name', 'ميكانيك'));
    }

    #[Test]
    public function student_manager_can_bulk_change_status_for_selected_students(): void
    {
        $this->withoutVite();
        $this->actingAsStudentManagerWeb();
        $schoolId = (int) session('current_school_id');

        $first = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-BULK-001',
            'status' => 1,
        ]);
        $second = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-BULK-002',
            'status' => 1,
        ]);

        $this->post('/students/bulk-status', [
            'student_ids' => [$first->id, $second->id],
            'status' => 2,
        ])->assertRedirect();

        $this->assertDatabaseHas($first->getTable(), [
            'id' => $first->id,
            'status' => 2,
        ]);
        $this->assertDatabaseHas($second->getTable(), [
            'id' => $second->id,
            'status' => 2,
        ]);
    }

    #[Test]
    public function student_viewer_cannot_bulk_change_status(): void
    {
        $this->withoutVite();
        $this->actingAsStudentViewerWeb();
        $schoolId = (int) session('current_school_id');

        $student = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-BULK-VIEW-001',
            'status' => 1,
        ]);

        $this->post('/students/bulk-status', [
            'student_ids' => [$student->id],
            'status' => 0,
        ])->assertForbidden();
    }

    #[Test]
    public function bulk_status_rejects_cross_school_student_ids(): void
    {
        $this->withoutVite();

        $schoolA = $this->createSchool('SCHOOL-A', 'School A');
        $schoolB = $this->createSchool('SCHOOL-B', 'School B');
        $foreign = $this->createStudentForSchool($schoolB, [
            'student_code' => 'STU-BULK-X-001',
            'status' => 1,
        ]);

        $this->actingAsStudentManagerWeb(null, $schoolA);

        $this->post('/students/bulk-status', [
            'student_ids' => [$foreign->id],
            'status' => 4,
        ])->assertNotFound();
    }

    #[Test]
    public function student_manager_can_update_selected_student_row(): void
    {
        $this->withoutVite();
        $this->actingAsStudentManagerWeb();
        $schoolId = (int) session('current_school_id');

        $student = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-UPD-001',
            'first_name' => 'Old',
            'last_name' => 'Name',
            'full_name' => 'Old Name',
            'national_id' => 'NAT-KEEP-001',
            'mother_name' => 'Original Mother',
            'department_name' => 'صناعي',
            'birth_date' => '2012-05-01',
        ]);

        $this->put("/students/{$student->id}", [
            'first_name' => 'New',
            'last_name' => 'Name',
            'father_name' => 'Father',
            'birth_date' => '2012-05-01',
            'department_name' => 'كهرباء',
            'specialization_name' => 'الكترونيك',
            'admitted_class_name' => 'الثالث',
        ])->assertRedirect();

        $this->assertDatabaseHas($student->getTable(), [
            'id' => $student->id,
            'first_name' => 'New',
            'father_name' => 'Father',
            'department_name' => 'كهرباء',
            'specialization_name' => 'الكترونيك',
            'admitted_class_name' => 'الثالث',
            'national_id' => 'NAT-KEEP-001',
            'mother_name' => 'Original Mother',
        ]);
    }

    #[Test]
    public function student_manager_can_update_visible_row_fields_without_changing_status(): void
    {
        $this->withoutVite();
        $this->actingAsStudentManagerWeb();
        $schoolId = (int) session('current_school_id');

        $student = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-UPD-VIS-001',
            'first_name' => 'Visible',
            'last_name' => 'Row',
            'full_name' => 'Visible Row',
            'national_id' => 'NAT-VIS-001',
            'status' => 1,
            'gender' => 1,
            'birth_date' => '2012-05-01',
            'governorate' => 'بغداد',
            'neighborhood' => 'الكرادة',
            'stage_name' => 'ابتدائي',
            'mobile' => '07700000000',
        ]);

        $this->put("/students/{$student->id}", [
            'first_name' => 'Visible',
            'last_name' => 'Row',
            'birth_date' => '2012-05-01',
            'governorate' => 'النجف',
            'neighborhood' => 'المركز',
            'gender' => 2,
            'stage_name' => 'متوسطة',
            'previous_school_name' => 'مدرسة الرافدين',
            'mobile' => '07811111111',
            'national_id' => 'NAT-VIS-001',
            'mother_name' => null,
            'religion' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas($student->getTable(), [
            'id' => $student->id,
            'status' => 1,
            'governorate' => 'النجف',
            'neighborhood' => 'المركز',
            'gender' => 2,
            'stage_name' => 'متوسطة',
            'previous_school_name' => 'مدرسة الرافدين',
            'mobile' => '07811111111',
            'national_id' => 'NAT-VIS-001',
        ]);
    }

    #[Test]
    public function student_row_update_rejects_status_field(): void
    {
        $this->withoutVite();
        $this->actingAsStudentManagerWeb();
        $schoolId = (int) session('current_school_id');

        $student = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-UPD-STATUS-001',
            'first_name' => 'Keep',
            'last_name' => 'Status',
            'full_name' => 'Keep Status',
            'status' => 1,
            'birth_date' => '2012-05-01',
        ]);

        $this->from('/students')->put("/students/{$student->id}", [
            'first_name' => 'Keep',
            'last_name' => 'Status',
            'birth_date' => '2012-05-01',
            'status' => 4,
        ])->assertRedirect('/students')->assertSessionHasErrors('status');

        $this->assertDatabaseHas($student->getTable(), [
            'id' => $student->id,
            'status' => 1,
        ]);
    }

    #[Test]
    public function student_manager_can_update_student_from_view_form(): void
    {
        $this->withoutVite();
        $this->actingAsStudentManagerWeb();
        $schoolId = (int) session('current_school_id');

        $student = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-UPD-FORM-001',
            'first_name' => 'Form',
            'last_name' => 'Student',
            'full_name' => 'Form Student',
            'national_id' => 'NAT-FORM-001',
            'mother_name' => 'Old Mother',
            'governorate' => 'بغداد',
            'birth_date' => '2012-05-01',
        ]);

        $this->put("/students/{$student->id}", [
            'first_name' => 'Form',
            'last_name' => 'Student',
            'birth_date' => '2012-05-01',
            'mother_name' => 'New Mother',
            'governorate' => 'البصرة',
            'gender' => 1,
            'religion' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas($student->getTable(), [
            'id' => $student->id,
            'mother_name' => 'New Mother',
            'governorate' => 'البصرة',
            'national_id' => 'NAT-FORM-001',
        ]);
    }

    #[Test]
    public function student_viewer_cannot_update_student_row(): void
    {
        $this->withoutVite();
        $this->actingAsStudentViewerWeb();
        $schoolId = (int) session('current_school_id');

        $student = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-UPD-VIEW-001',
            'first_name' => 'View',
            'last_name' => 'Only',
            'full_name' => 'View Only',
            'birth_date' => '2012-05-01',
        ]);

        $this->put("/students/{$student->id}", [
            'first_name' => 'Blocked',
            'last_name' => 'Only',
            'birth_date' => '2012-05-01',
        ])->assertForbidden();
    }
}
