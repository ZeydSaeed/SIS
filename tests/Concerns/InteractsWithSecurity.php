<?php

namespace Tests\Concerns;

use App\Database\SchemaHelper;
use App\Infrastructure\Persistence\Eloquent\EnrollmentClassRecord;
use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Infrastructure\Persistence\Eloquent\EnrollmentSectionRecord;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

trait InteractsWithSecurity
{
    protected function createSchool(string $code, string $name): int
    {
        $table = SchemaHelper::qualified('organization', 'schools');

        $existing = DB::table($table)->where('code', $code)->value('id');
        if ($existing !== null) {
            return (int) $existing;
        }

        $directorateId = DB::table(SchemaHelper::qualified('organization', 'directorates'))->value('id');
        if ($directorateId === null) {
            $ministryId = DB::table(SchemaHelper::qualified('organization', 'ministries'))->insertGetId([
                'code' => 'MIN-TEST',
                'name' => 'Test Ministry',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $directorateId = DB::table(SchemaHelper::qualified('organization', 'directorates'))->insertGetId([
                'code' => 'DIR-'.strtoupper($code),
                'ministry_id' => $ministryId,
                'name' => 'Test Directorate',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return (int) DB::table($table)->insertGetId([
            'code' => $code,
            'directorate_id' => $directorateId,
            'name' => $name,
            'school_type' => 2,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function actingAsStudentManager(?User $user = null, ?int $schoolId = null): User
    {
        $schoolId ??= $this->createSchool('SCHOOL-A', 'School A');
        $user ??= User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantStudentManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        return $user;
    }

    protected function actingAsStudentViewer(?User $user = null, ?int $schoolId = null): User
    {
        $schoolId ??= $this->createSchool('SCHOOL-A', 'School A');
        $user ??= User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantStudentViewer($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        return $user;
    }

    protected function actingAsAuthenticatedWithoutPermissions(?User $user = null): User
    {
        $user ??= User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    protected function actingAsStudentManagerForSchool(int $schoolId, ?User $user = null): User
    {
        $user ??= User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantStudentManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        return $user;
    }

    protected function actingAsStudentManagerWeb(?User $user = null, ?int $schoolId = null): User
    {
        $schoolId ??= $this->createSchool('SCHOOL-A', 'School A');
        $user ??= User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantStudentManager($user, $schoolId);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $schoolId]);

        return $user;
    }

    protected function actingAsStudentViewerWeb(?User $user = null, ?int $schoolId = null): User
    {
        $schoolId ??= $this->createSchool('SCHOOL-A', 'School A');
        $user ??= User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantStudentViewer($user, $schoolId);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $schoolId]);

        return $user;
    }

    protected function withWebSchoolContext(int $schoolId): static
    {
        return $this->withSession(['current_school_id' => $schoolId]);
    }

    protected function actingAsEnrollmentManager(?User $user = null, ?int $schoolId = null): User
    {
        $schoolId ??= $this->createSchool('SCHOOL-A', 'School A');
        $user ??= User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantEnrollmentManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        return $user;
    }

    protected function actingAsEnrollmentManagerForSchool(int $schoolId, ?User $user = null): User
    {
        $user ??= User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantEnrollmentManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        return $user;
    }

    protected function actingAsGradesManager(?User $user = null, ?int $schoolId = null): User
    {
        $schoolId ??= $this->createSchool('SCHOOL-A', 'School A');
        $user ??= User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantGradesManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        return $user;
    }

    protected function actingAsGradesManagerForSchool(int $schoolId, ?User $user = null): User
    {
        $user ??= User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantGradesManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        return $user;
    }

    protected function actingAsGradesTeacher(?User $user = null, ?int $schoolId = null): User
    {
        $schoolId ??= $this->createSchool('SCHOOL-A', 'School A');
        $user ??= User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantGradesTeacher($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        return $user;
    }

    protected function actingAsGradesViewer(?User $user = null, ?int $schoolId = null): User
    {
        $schoolId ??= $this->createSchool('SCHOOL-A', 'School A');
        $user ??= User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantGradesViewer($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        return $user;
    }

    protected function actingAsAttendanceViewer(?User $user = null, ?int $schoolId = null): User
    {
        $schoolId ??= $this->createSchool('SCHOOL-A', 'School A');
        $user ??= User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantAttendanceViewer($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        return $user;
    }

    protected function actingAsAttendanceTeacher(?User $user = null, ?int $schoolId = null): User
    {
        $schoolId ??= $this->createSchool('SCHOOL-A', 'School A');
        $user ??= User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantAttendanceTeacher($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        return $user;
    }

    protected function actingAsAttendanceManager(?User $user = null, ?int $schoolId = null): User
    {
        $schoolId ??= $this->createSchool('SCHOOL-A', 'School A');
        $user ??= User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantAttendanceManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        return $user;
    }

    protected function actingAsAttendanceManagerForSchool(int $schoolId, ?User $user = null): User
    {
        $user ??= User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantAttendanceManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        return $user;
    }

    protected function createAcademicYear(string $code = 'AY-2026'): int
    {
        $table = SchemaHelper::qualified('academic', 'academic_years');
        $existing = DB::table($table)->where('code', $code)->value('id');
        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table($table)->insertGetId([
            'code' => $code,
            'name' => 'Academic Year '.$code,
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createGradeLevel(string $code = 'G10'): int
    {
        $table = SchemaHelper::qualified('academic', 'grade_levels');
        $existing = DB::table($table)->where('code', $code)->value('id');
        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table($table)->insertGetId([
            'code' => $code,
            'name' => 'Grade '.$code,
            'level_order' => 10,
            'education_stage' => 2,
            'status' => 1,
        ]);
    }

    protected function createClassForSchool(int $schoolId, int $academicYearId, ?int $gradeLevelId = null): EnrollmentClassRecord
    {
        $gradeLevelId ??= $this->createGradeLevel();
        $record = new EnrollmentClassRecord;
        $record->forceFill([
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'grade_level_id' => $gradeLevelId,
            'code' => 'CLS-'.uniqid(),
            'name' => 'Test Class',
            'status' => 1,
        ]);
        $record->save();

        return $record;
    }

    protected function createSectionForClass(int $classId): EnrollmentSectionRecord
    {
        $record = new EnrollmentSectionRecord;
        $record->forceFill([
            'class_id' => $classId,
            'code' => 'SEC-'.uniqid(),
            'name' => 'Test Section',
            'status' => 1,
        ]);
        $record->save();

        return $record;
    }

    protected function createStudentForSchool(int $schoolId, array $attributes = []): StudentRecord
    {
        $record = new StudentRecord;
        $record->forceFill(array_merge([
            'school_id' => $schoolId,
            'student_code' => 'STU-TEST-'.uniqid(),
            'first_name' => 'Test',
            'last_name' => 'Student',
            'full_name' => 'Test Student',
            'gender' => 1,
            'birth_date' => '2010-01-01',
            'status' => 1,
        ], $attributes));
        $record->save();

        return $record;
    }

    protected function createActiveEnrollmentForSchool(
        int $schoolId,
        ?int $yearId = null,
        ?StudentRecord $student = null,
    ): EnrollmentRecord {
        $yearId ??= $this->createAcademicYear();
        $student ??= $this->createStudentForSchool($schoolId);
        $class = $this->createClassForSchool($schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);

        $enrollment = new EnrollmentRecord;
        $enrollment->forceFill([
            'student_id' => $student->id,
            'academic_year_id' => $yearId,
            'school_id' => $schoolId,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'enrollment_number' => 'ENR-'.uniqid(),
            'status' => 1,
            'effective_from' => '2026-09-01',
        ]);
        $enrollment->save();

        return $enrollment;
    }
}
