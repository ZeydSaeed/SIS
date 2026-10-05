<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * «المديريات والمدارس»: directorates → the user's schools → each school's branches → departments.
 */
final class OrganizationDirectorateSchoolPagePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function page_nests_the_users_schools_and_their_branches_under_directorates(): void
    {
        $this->withoutVite();
        $schoolId = $this->createSchool('SCH-DSP', 'اعدادية النهرين المهنية');
        $foreignSchoolId = $this->createSchool('SCH-DSF', 'مدرسة غير مرتبطة');
        $directorateId = (int) DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('id', $schoolId)->value('directorate_id');

        $user = User::factory()->create();
        $seeder = app(SecurityPermissionSeeder::class);
        $seeder->grantStudentManager($user, $schoolId);
        $seeder->assignRole($user, 'admission_manager', $schoolId);
        $seeder->grantSchoolManager($user, $schoolId);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $schoolId]);

        $this->withHeader('X-Idempotency-Key', 'dsp-branch-1')
            ->post('/organization/branches', ['name' => 'الصناعي'])->assertSessionHasNoErrors();
        $branchId = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))
            ->where('school_id', $schoolId)->where('name', 'الصناعي')->value('id');
        $this->withHeader('X-Idempotency-Key', 'dsp-department-1')
            ->post('/organization/departments', ['name' => 'الميكانيك', 'branch_id' => $branchId])->assertSessionHasNoErrors();

        $this->get('/organization/directorates-schools?school='.$schoolId.'&edit=1')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('organization/directorates-schools')
                ->where('authorization.can_manage_schools', true)
                ->where('current_school_id', $schoolId)
                ->where('focus', ['school_id' => $schoolId, 'edit' => true])
                ->where('directorates', function ($directorates) use ($directorateId, $schoolId, $foreignSchoolId): bool {
                    $directorate = collect($directorates)->firstWhere('id', $directorateId);
                    $schools = collect($directorate['schools'] ?? []);
                    $school = $schools->firstWhere('id', $schoolId);

                    // Only the user's schools are listed; branches carry their departments.
                    return $school !== null
                        && $schools->firstWhere('id', $foreignSchoolId) === null
                        && collect($school['branches'])->pluck('name')->all() === ['الصناعي']
                        && collect($school['branches'][0]['departments'])->pluck('name')->all() === ['الميكانيك'];
                }));
    }

    #[Test]
    public function users_without_organization_permissions_cannot_open_the_page(): void
    {
        $schoolId = $this->createSchool('SCH-DSN', 'Registry School');
        $user = User::factory()->create();
        $seeder = app(SecurityPermissionSeeder::class);
        $seeder->grantStudentManager($user, $schoolId);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $schoolId]);

        $this->get('/organization/directorates-schools')->assertForbidden();
    }
}
