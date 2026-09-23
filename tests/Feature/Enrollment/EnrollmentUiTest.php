<?php

namespace Tests\Feature\Enrollment;

use App\Database\SchemaHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
                ->where('enrollments.status_progress.stages.4.status', 4)
                ->where('enrollments.status_progress.stages.5.status', 3)
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
                ->where('enrollments.status_progress.stages.4.count', 0)
                ->where('enrollments.status_progress.stages.5.count', 0));
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
    public function enrollment_list_filters_by_class(): void
    {
        $this->withoutVite();
        $this->actingAsEnrollmentManagerWeb();
        $schoolId = (int) session('current_school_id');
        $yearId = $this->createAcademicYear('AY-ENR-CLASS');

        $firstClass = $this->createClassForSchool($schoolId, $yearId);
        $firstClass->forceFill(['name' => 'الأول', 'code' => 'CLS-1'])->save();
        $firstSection = $this->createSectionForClass((int) $firstClass->id);

        $secondClass = $this->createClassForSchool($schoolId, $yearId);
        $secondClass->forceFill(['name' => 'الثاني', 'code' => 'CLS-2'])->save();
        $secondSection = $this->createSectionForClass((int) $secondClass->id);

        $firstStudent = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-CLASS-1',
            'first_name' => 'FirstClass',
            'last_name' => 'Student',
            'full_name' => 'FirstClass Student',
        ]);
        $secondStudent = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-CLASS-2',
            'first_name' => 'SecondClass',
            'last_name' => 'Student',
            'full_name' => 'SecondClass Student',
        ]);

        $firstEnrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId, $firstStudent);
        $firstEnrollment->forceFill([
            'class_id' => $firstClass->id,
            'section_id' => $firstSection->id,
        ])->save();

        $secondEnrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId, $secondStudent);
        $secondEnrollment->forceFill([
            'class_id' => $secondClass->id,
            'section_id' => $secondSection->id,
        ])->save();

        $this->get("/enrollments?academic_year_id={$yearId}&class_id={$firstClass->id}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('filters.class_id', (int) $firstClass->id)
                ->has('enrollments.data', 1)
                ->where('enrollments.data.0.student_code', 'STU-ENR-CLASS-1')
                ->where('enrollments.data.0.class_name', 'الأول'));

        $this->get("/enrollments?academic_year_id={$yearId}&class_id={$secondClass->id}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('filters.class_id', (int) $secondClass->id)
                ->has('enrollments.data', 1)
                ->where('enrollments.data.0.student_code', 'STU-ENR-CLASS-2')
                ->where('enrollments.data.0.class_name', 'الثاني'));
    }

    #[Test]
    public function enrollment_list_filters_by_specialization_via_student_name(): void
    {
        $this->withoutVite();
        $this->actingAsEnrollmentManagerWeb();
        $schoolId = (int) session('current_school_id');
        $yearId = $this->createAcademicYear('AY-ENR-SPEC');

        $specId = (int) DB::table(SchemaHelper::qualified('vocational', 'specializations'))->insertGetId([
            'school_id' => $schoolId,
            'code' => 'SPEC-ELEC',
            'name' => 'الكهرباء',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $matched = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-SPEC-1',
            'first_name' => 'SpecMatch',
            'last_name' => 'Student',
            'full_name' => 'SpecMatch Student',
            'specialization_name' => 'الكهرباء',
        ]);
        $other = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-SPEC-2',
            'first_name' => 'SpecOther',
            'last_name' => 'Student',
            'full_name' => 'SpecOther Student',
            'specialization_name' => 'الميكانيك',
        ]);
        $this->createActiveEnrollmentForSchool($schoolId, $yearId, $matched);
        $this->createActiveEnrollmentForSchool($schoolId, $yearId, $other);

        $this->get("/enrollments?academic_year_id={$yearId}&specialization_id={$specId}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('filters.specialization_id', $specId)
                ->has('enrollments.data', 1)
                ->where('enrollments.data.0.student_code', 'STU-ENR-SPEC-1'));
    }

    #[Test]
    public function enrollment_bulk_placement_updates_class_for_selected_rows(): void
    {
        $this->withoutVite();
        $this->actingAsEnrollmentManagerWeb();
        $schoolId = (int) session('current_school_id');
        $yearId = $this->createAcademicYear('AY-ENR-BULK-PLACE');
        $student = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-BULK-1',
            'first_name' => 'BulkPlace',
            'last_name' => 'Student',
            'full_name' => 'BulkPlace Student',
        ]);
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId, $student);
        $targetClass = $this->createClassForSchool($schoolId, $yearId);
        $targetSection = $this->createSectionForClass((int) $targetClass->id);

        $this->post('/enrollments/bulk-placement', [
            'enrollment_ids' => [(int) $enrollment->id],
            'class_id' => (int) $targetClass->id,
        ])->assertRedirect();

        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollments'), [
            'id' => $enrollment->id,
            'class_id' => $targetClass->id,
            'section_id' => $targetSection->id,
        ]);
    }

    #[Test]
    public function enrollment_bulk_placement_updates_gender_for_selected_rows(): void
    {
        $this->withoutVite();
        $this->actingAsEnrollmentManagerWeb();
        $schoolId = (int) session('current_school_id');
        $yearId = $this->createAcademicYear('AY-ENR-BULK-GENDER');
        $student = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-BULK-G',
            'first_name' => 'BulkGender',
            'last_name' => 'Student',
            'full_name' => 'BulkGender Student',
            'gender' => 1,
        ]);
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId, $student);

        $this->post('/enrollments/bulk-placement', [
            'enrollment_ids' => [(int) $enrollment->id],
            'gender' => 2,
        ])->assertRedirect();

        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'students'), [
            'id' => $student->id,
            'gender' => 2,
        ]);
    }

    #[Test]
    public function enrollment_bulk_placement_updates_section_and_class_for_multiple_rows(): void
    {
        $this->withoutVite();
        $this->actingAsEnrollmentManagerWeb();
        $schoolId = (int) session('current_school_id');
        $yearId = $this->createAcademicYear('AY-ENR-BULK-SEC');

        $studentA = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-SEC-A',
            'first_name' => 'SecA',
            'last_name' => 'Student',
            'full_name' => 'SecA Student',
        ]);
        $studentB = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-SEC-B',
            'first_name' => 'SecB',
            'last_name' => 'Student',
            'full_name' => 'SecB Student',
        ]);

        $enrollmentA = $this->createActiveEnrollmentForSchool($schoolId, $yearId, $studentA);
        $enrollmentB = $this->createActiveEnrollmentForSchool($schoolId, $yearId, $studentB);

        $targetClass = $this->createClassForSchool($schoolId, $yearId);
        $targetSection = $this->createSectionForClass((int) $targetClass->id);

        $this->post('/enrollments/bulk-placement', [
            'enrollment_ids' => [(int) $enrollmentA->id, (int) $enrollmentB->id],
            'section_id' => (int) $targetSection->id,
        ])->assertRedirect();

        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollments'), [
            'id' => $enrollmentA->id,
            'class_id' => $targetClass->id,
            'section_id' => $targetSection->id,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollments'), [
            'id' => $enrollmentB->id,
            'class_id' => $targetClass->id,
            'section_id' => $targetSection->id,
        ]);
    }

    #[Test]
    public function enrollment_bulk_placement_updates_gender_for_single_multi_and_select_all(): void
    {
        $this->withoutVite();
        $this->actingAsEnrollmentManagerWeb();
        $schoolId = (int) session('current_school_id');
        $yearId = $this->createAcademicYear('AY-ENR-BULK-SEL');

        $students = [];
        $enrollments = [];
        foreach (['S1', 'S2', 'S3', 'S4'] as $index => $code) {
            $student = $this->createStudentForSchool($schoolId, [
                'student_code' => 'STU-ENR-SEL-'.$code,
                'first_name' => 'Sel'.$code,
                'last_name' => 'Student',
                'full_name' => 'Sel'.$code.' Student',
                'gender' => 1,
            ]);
            $students[] = $student;
            $enrollments[] = $this->createActiveEnrollmentForSchool($schoolId, $yearId, $student);
        }

        // Single selection
        $this->post('/enrollments/bulk-placement', [
            'enrollment_ids' => [(int) $enrollments[0]->id],
            'gender' => 2,
        ])->assertRedirect();
        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'students'), [
            'id' => $students[0]->id,
            'gender' => 2,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'students'), [
            'id' => $students[1]->id,
            'gender' => 1,
        ]);

        // Multi selection
        $this->post('/enrollments/bulk-placement', [
            'enrollment_ids' => [(int) $enrollments[1]->id, (int) $enrollments[2]->id],
            'gender' => 2,
        ])->assertRedirect();
        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'students'), [
            'id' => $students[1]->id,
            'gender' => 2,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'students'), [
            'id' => $students[2]->id,
            'gender' => 2,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'students'), [
            'id' => $students[3]->id,
            'gender' => 1,
        ]);

        // Select-all style payload (every visible row id)
        $allIds = array_map(static fn ($enrollment): int => (int) $enrollment->id, $enrollments);
        $this->post('/enrollments/bulk-placement', [
            'enrollment_ids' => $allIds,
            'gender' => 1,
        ])->assertRedirect();
        foreach ($students as $student) {
            $this->assertDatabaseHas(SchemaHelper::qualified('students', 'students'), [
                'id' => $student->id,
                'gender' => 1,
            ]);
        }
    }

    #[Test]
    public function enrollment_bulk_placement_select_all_skips_inactive_and_updates_active(): void
    {
        $this->withoutVite();
        $this->actingAsEnrollmentManagerWeb();
        $schoolId = (int) session('current_school_id');
        $yearId = $this->createAcademicYear('AY-ENR-BULK-MIX');

        $activeStudent = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-MIX-A',
            'first_name' => 'MixA',
            'last_name' => 'Student',
            'full_name' => 'MixA Student',
            'gender' => 1,
        ]);
        $inactiveStudent = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-MIX-I',
            'first_name' => 'MixI',
            'last_name' => 'Student',
            'full_name' => 'MixI Student',
            'gender' => 1,
        ]);
        $active = $this->createActiveEnrollmentForSchool($schoolId, $yearId, $activeStudent);
        $inactive = $this->createActiveEnrollmentForSchool($schoolId, $yearId, $inactiveStudent);
        $inactive->forceFill(['status' => 2, 'effective_to' => '2026-09-01'])->save();

        $targetClass = $this->createClassForSchool($schoolId, $yearId);
        $targetSection = $this->createSectionForClass((int) $targetClass->id);

        $this->post('/enrollments/bulk-placement', [
            'enrollment_ids' => [(int) $active->id, (int) $inactive->id],
            'section_id' => (int) $targetSection->id,
        ])->assertRedirect();

        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollments'), [
            'id' => $active->id,
            'class_id' => $targetClass->id,
            'section_id' => $targetSection->id,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollments'), [
            'id' => $inactive->id,
            'class_id' => $inactive->class_id,
            'section_id' => $inactive->section_id,
            'status' => 2,
        ]);
    }

    #[Test]
    public function enrollment_bulk_status_matrix_covers_mixed_selections_and_all_targets(): void
    {
        $this->withoutVite();
        $this->actingAsEnrollmentManagerWeb();
        $schoolId = (int) session('current_school_id');
        $yearId = $this->createAcademicYear('AY-ENR-STATUS-MX');
        $enrollmentTable = SchemaHelper::qualified('enrollment', 'enrollments');
        $studentTable = SchemaHelper::qualified('students', 'students');

        $make = function (string $code) use ($schoolId, $yearId) {
            $student = $this->createStudentForSchool($schoolId, [
                'student_code' => 'STU-ENR-MX-'.$code,
                'first_name' => 'Mx'.$code,
                'last_name' => 'Student',
                'full_name' => 'Mx'.$code.' Student',
            ]);

            return $this->createActiveEnrollmentForSchool($schoolId, $yearId, $student);
        };

        $assertPair = function ($enrollment, int $studentStatus, int $enrollmentStatus) use ($enrollmentTable, $studentTable): void {
            $enrollment->refresh();
            $this->assertDatabaseHas($enrollmentTable, [
                'id' => $enrollment->id,
                'status' => $enrollmentStatus,
            ]);
            $this->assertDatabaseHas($studentTable, [
                'id' => $enrollment->student_id,
                'status' => $studentStatus,
            ]);
        };

        $postStatus = function (array $ids, int $status) {
            return $this->post('/enrollments/bulk-status', [
                'enrollment_ids' => array_map('intval', $ids),
                'status' => $status,
                'effective_to' => '2026-09-22',
            ])->assertRedirect();
        };

        // Student Active (1) → enrollment Active (1)
        // Student Inactive/Suspended/Graduated (0/2/3) → enrollment Inactive (0)
        // Student Withdrawn (4) → enrollment Cancelled (2)
        $mapEnrollment = static fn (int $studentStatus): int => match ($studentStatus) {
            1 => 1,
            4 => 2,
            default => 0,
        };

        $activeA = $make('A');
        $activeB = $make('B');
        $postStatus([$activeA->id, $activeB->id], 0);
        $assertPair($activeA, 0, 0);
        $assertPair($activeB, 0, 0);

        $postStatus([$activeA->id], 2); // suspended
        $assertPair($activeA, 2, 0);

        $postStatus([$activeA->id], 1); // reactivate
        $assertPair($activeA, 1, 1);

        $postStatus([$activeB->id], 4); // withdrawn
        $assertPair($activeB, 4, 2);

        $postStatus([$activeB->id], 3); // graduated
        $assertPair($activeB, 3, 0);

        // Cartesian student statuses
        $targets = [1, 0, 2, 3, 4];
        $seq = 0;
        foreach ($targets as $from) {
            foreach ($targets as $to) {
                $seq++;
                $row = $make('X'.$seq);
                $postStatus([$row->id], $from);
                $assertPair($row, $from, $mapEnrollment($from));
                $postStatus([$row->id], $to);
                $assertPair($row, $to, $mapEnrollment($to));
                if ($to === 1) {
                    $this->assertDatabaseHas($enrollmentTable, [
                        'id' => $row->id,
                        'status' => 1,
                        'effective_to' => null,
                    ]);
                }
            }
        }

        // Future effective_from must still allow inactive (effective_to clamped)
        $future = $make('F');
        $future->forceFill(['effective_from' => '2026-12-01'])->save();
        $this->post('/enrollments/bulk-status', [
            'enrollment_ids' => [(int) $future->id],
            'status' => 0,
            'effective_to' => '2026-09-22',
        ])->assertRedirect();
        $future->refresh();
        $this->assertSame(0, (int) $future->status);
        $this->assertNotNull($future->effective_to);
        $this->assertSame('2026-12-01', substr((string) $future->effective_to, 0, 10));
    }

    #[Test]
    public function enrollment_web_delete_action_cancels_selected_enrollment(): void
    {
        $this->withoutVite();
        $this->actingAsEnrollmentManagerWeb();
        $schoolId = (int) session('current_school_id');
        $yearId = $this->createAcademicYear('AY-ENR-DEL-UI');
        $student = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-DEL-UI',
            'first_name' => 'DelUi',
            'last_name' => 'Student',
            'full_name' => 'DelUi Student',
        ]);
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId, $student);

        $this->post('/enrollments/bulk-status', [
            'enrollment_ids' => [(int) $enrollment->id],
            'status' => 4,
            'effective_to' => '2026-09-22',
        ])->assertRedirect();

        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollments'), [
            'id' => $enrollment->id,
            'status' => 2,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'students'), [
            'id' => $student->id,
            'status' => 4,
        ]);
    }

    #[Test]
    public function enrollment_web_update_placement_redirects_back(): void
    {
        $this->withoutVite();
        $this->actingAsEnrollmentManagerWeb();
        $schoolId = (int) session('current_school_id');
        $yearId = $this->createAcademicYear('AY-ENR-PUT-UI');
        $student = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-ENR-PUT-UI',
            'first_name' => 'PutUi',
            'last_name' => 'Student',
            'full_name' => 'PutUi Student',
        ]);
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId, $student);
        $newClass = $this->createClassForSchool($schoolId, $yearId);
        $newSection = $this->createSectionForClass((int) $newClass->id);

        $this->from('/enrollments')
            ->put("/enrollments/{$enrollment->id}", [
                'class_id' => (int) $newClass->id,
                'section_id' => (int) $newSection->id,
                'stage_name' => 'مرحلة اختبار',
                'gender' => 2,
                'academic_year_id' => $yearId,
            ])
            ->assertRedirect('/enrollments');

        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollments'), [
            'id' => $enrollment->id,
            'class_id' => $newClass->id,
            'section_id' => $newSection->id,
            'academic_year_id' => $yearId,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'students'), [
            'id' => $student->id,
            'gender' => 2,
            'stage_name' => 'مرحلة اختبار',
        ]);
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
