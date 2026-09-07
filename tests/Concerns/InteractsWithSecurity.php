<?php

namespace Tests\Concerns;

use App\Database\SchemaHelper;
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
}
