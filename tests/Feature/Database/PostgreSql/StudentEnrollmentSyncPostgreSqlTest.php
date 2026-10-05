<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Infrastructure\Jobs\ProcessOutboxJob;
use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Infrastructure\Persistence\Outbox\EloquentOutboxRepository;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * One placement for the student page and the enrollments page:
 * - identity edits are read live by the enrollments page;
 * - a department change on the student page moves the active enrollment (history +
 *   the new department's curriculum);
 * - a department change on the enrollments page updates the student;
 * - the class of an enrolled student is changed on the enrollments page only;
 * - the student stores ids only — an unknown department name is rejected.
 */
final class StudentEnrollmentSyncPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    private int $schoolId;

    private int $yearId;

    private int $branchId;

    private int $electricity;

    private int $mechanics;

    private int $firstGrade;

    private int $mechanicsSubject;

    private StudentRecord $student;

    private EnrollmentRecord $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->schoolId = $this->createSchool('SCH-SYNC', 'Sync School');
        $this->yearId = $this->createAcademicYear('AY-SYNC');
        $this->firstGrade = $this->gradeLevelNamed('الأول');
        $this->gradeLevelNamed('الثاني');
        $this->branchId = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
            'school_id' => $this->schoolId, 'code' => 'BR-SYNC', 'name' => 'الصناعي', 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->electricity = $this->createDepartmentForSchool($this->schoolId, 'كهرباء', $this->branchId);
        $this->mechanics = $this->createDepartmentForSchool($this->schoolId, 'ميكانيك', $this->branchId);

        $this->mechanicsSubject = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'MECH-SYNC', 'name' => 'ميكانيك عام', 'name_en' => 'Mechanics', 'subject_type' => 1,
            'credit_hours' => 3, 'max_grade' => 100, 'pass_grade' => 50, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->createCurriculumWithSubjects(
            $this->schoolId, $this->yearId, $this->firstGrade, [$this->mechanicsSubject], departmentId: $this->mechanics,
        );

        $class = $this->createClassForSchool($this->schoolId, $this->yearId, $this->firstGrade);
        $section = $this->createSectionForClass((int) $class->id);
        $this->student = $this->createStudentForSchool($this->schoolId, [
            'first_name' => 'سالم', 'last_name' => 'الحسن', 'full_name' => 'سالم الحسن', 'birth_date' => '2010-01-01',
            'branch_id' => $this->branchId, 'department_id' => $this->electricity, 'grade_level_id' => $this->firstGrade,
        ]);
        $this->enrollment = new EnrollmentRecord;
        $this->enrollment->forceFill([
            'student_id' => $this->student->id, 'academic_year_id' => $this->yearId, 'school_id' => $this->schoolId,
            'class_id' => $class->id, 'section_id' => $section->id, 'status' => 1, 'effective_from' => '2026-09-01',
            'branch_id' => $this->branchId, 'department_id' => $this->electricity,
        ]);
        $this->enrollment->save();

        $user = User::factory()->create();
        $seeder = app(SecurityPermissionSeeder::class);
        $seeder->grantStudentManager($user, $this->schoolId);
        $seeder->grantEnrollmentManager($user, $this->schoolId);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $this->schoolId]);
    }

    #[Test]
    public function identity_edit_on_student_page_shows_on_enrollments_page(): void
    {
        $this->put("/students/{$this->student->id}", [
            'first_name' => 'سليم', 'last_name' => 'الحسن', 'birth_date' => '2010-01-01',
        ])->assertRedirect()->assertSessionMissing('error');

        $this->get("/enrollments?academic_year_id={$this->yearId}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('enrollments.data.0.student_first_name', 'سليم')
                ->where('enrollments.data.0.department_name', 'كهرباء'));
    }

    #[Test]
    public function department_change_on_student_page_moves_the_active_enrollment_and_its_curriculum(): void
    {
        $this->put("/students/{$this->student->id}", [
            'first_name' => 'سالم', 'last_name' => 'الحسن', 'birth_date' => '2010-01-01',
            'branch_id' => $this->branchId, 'department_name' => 'ميكانيك',
        ])->assertRedirect()->assertSessionMissing('error');

        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'students'), [
            'id' => $this->student->id, 'department_id' => $this->mechanics, 'grade_level_id' => $this->firstGrade,
        ]);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $this->schoolId]);
        $enrollments = SchemaHelper::qualified('enrollment', 'enrollments');
        $active = DB::table($enrollments)->where('student_id', $this->student->id)->where('status', 1)->whereNull('effective_to')->first();
        $this->assertNotNull($active);
        $this->assertSame($this->mechanics, (int) $active->department_id);
        $this->assertSame((int) $this->enrollment->class_id, (int) $active->class_id, 'class and section stay');
        // Placement history: the electricity segment is superseded, not overwritten.
        $this->assertDatabaseHas($enrollments, [
            'id' => $this->enrollment->id, 'department_id' => $this->electricity, 'status' => 5,
        ]);

        // The new department's curriculum is applied from the outbox.
        $outbox = app(EloquentOutboxRepository::class);
        for ($run = 0; $run < 20 && $outbox->fetchUnprocessed(1) !== []; $run++) {
            (new ProcessOutboxJob)->handle($outbox);
        }
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $this->schoolId]);
        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollment_subjects'), [
            'enrollment_id' => $active->id, 'subject_id' => $this->mechanicsSubject, 'status' => 1,
        ]);

        $this->get("/enrollments?academic_year_id={$this->yearId}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('enrollments.data.0.department_name', 'ميكانيك'));
    }

    #[Test]
    public function department_change_on_enrollments_page_updates_the_student(): void
    {
        $this->put("/enrollments/{$this->enrollment->id}", [
            'class_id' => $this->enrollment->class_id,
            'section_id' => $this->enrollment->section_id,
            'branch_id' => $this->branchId,
            'department_id' => $this->mechanics,
        ])->assertRedirect()->assertSessionMissing('error');

        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'students'), [
            'id' => $this->student->id, 'department_id' => $this->mechanics,
        ]);
        $this->get("/students?academic_year_id={$this->yearId}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('students.data.0.department_name', 'ميكانيك')
                ->where('students.data.0.admitted_class_name', 'الأول'));
    }

    #[Test]
    public function class_of_an_enrolled_student_is_changed_on_the_enrollments_page_only(): void
    {
        $this->get("/students?academic_year_id={$this->yearId}")
            ->assertInertia(fn ($page) => $page
                ->where('students.data.0.active_enrollment_id', $this->enrollment->id)
                ->where('students.data.0.enrollment_grade_name', 'الأول'));

        $this->from('/students')->put("/students/{$this->student->id}", [
            'first_name' => 'سالم', 'last_name' => 'الحسن', 'birth_date' => '2010-01-01',
            'admitted_class_name' => 'الثاني',
        ])->assertRedirect('/students')->assertSessionHas('error', 'student.placement.grade_locked');

        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'students'), [
            'id' => $this->student->id, 'grade_level_id' => $this->firstGrade,
        ]);
    }

    #[Test]
    public function unknown_department_name_is_rejected_because_only_ids_are_stored(): void
    {
        $this->from('/students')->put("/students/{$this->student->id}", [
            'first_name' => 'سالم', 'last_name' => 'الحسن', 'birth_date' => '2010-01-01',
            'department_name' => 'قسم غير موجود',
        ])->assertRedirect('/students')->assertSessionHas('error', 'student.placement.department_unknown');

        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'students'), [
            'id' => $this->student->id, 'department_id' => $this->electricity,
        ]);
    }

    #[Test]
    public function not_enrolled_student_department_change_touches_only_the_student(): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $this->schoolId]);
        $this->enrollment->forceFill(['status' => 2, 'effective_to' => '2026-09-30'])->save();

        $this->put("/students/{$this->student->id}", [
            'first_name' => 'سالم', 'last_name' => 'الحسن', 'birth_date' => '2010-01-01',
            'branch_id' => $this->branchId, 'department_name' => 'ميكانيك',
        ])->assertRedirect()->assertSessionMissing('error');

        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'students'), [
            'id' => $this->student->id, 'department_id' => $this->mechanics,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollments'), [
            'id' => $this->enrollment->id, 'department_id' => $this->electricity, 'status' => 2,
        ]);
    }
}
