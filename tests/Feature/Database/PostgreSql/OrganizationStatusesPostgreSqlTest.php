<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use App\Security\Authorization\SchoolScopeService;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * Statuses نشط (1) / غير نشط (2) / مؤرشف (3) for schools, directorates, branches and
 * departments — set when adding and when editing; «حذف» archives.
 */
final class OrganizationStatusesPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    private int $schoolId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->schoolId = $this->createSchool('SCH-STS', 'اعدادية النهرين المهنية');

        $user = User::factory()->create();
        $seeder = app(SecurityPermissionSeeder::class);
        $seeder->grantStudentManager($user, $this->schoolId);
        $seeder->assignRole($user, 'admission_manager', $this->schoolId);
        $seeder->grantSchoolManager($user, $this->schoolId);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $this->schoolId]);
    }

    #[Test]
    public function school_and_directorate_take_a_status_on_add_and_edit(): void
    {
        $directorateId = $this->directorateOfSchool($this->schoolId);

        $this->send('post', '/organization/schools', ['name' => 'اعدادية الغد', 'directorate_id' => $directorateId, 'status' => 2])
            ->assertSessionHasNoErrors();
        $school = (int) DB::table(SchemaHelper::qualified('organization', 'schools'))->where('name', 'اعدادية الغد')->value('id');
        $this->assertDatabaseHas(SchemaHelper::qualified('organization', 'schools'), ['id' => $school, 'status' => 2]);

        // The creator was just linked to the new school; a fresh request sees the new membership
        // (the test app reuses the SchoolScopeService singleton and its per-request memo).
        $this->app->forgetInstance(SchoolScopeService::class);
        $this->send('post', "/organization/schools/{$school}/archive")->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas(SchemaHelper::qualified('organization', 'schools'), ['id' => $school, 'status' => 3]);
        $this->send('post', "/organization/schools/{$school}/reactivate")->assertSessionHasNoErrors();
        $this->assertDatabaseHas(SchemaHelper::qualified('organization', 'schools'), ['id' => $school, 'status' => 1]);
        $this->send('post', '/organization/schools', ['name' => 'حالة خاطئة', 'directorate_id' => $directorateId, 'status' => 9])
            ->assertSessionHasErrors(['status']);

        // A directorate can be added archived (empty) but not inactive with schools placed in it.
        $this->send('post', '/organization/directorates', ['name' => 'تربية المستقبل', 'status' => 3])->assertSessionHasNoErrors();
        $this->assertDatabaseHas(SchemaHelper::qualified('organization', 'directorates'), ['name' => 'تربية المستقبل', 'status' => 3]);
        $this->send('post', '/organization/directorates', ['name' => 'تربية مرفوضة', 'status' => 2, 'school_ids' => [$this->schoolId]])
            ->assertSessionHasErrors(['directorate' => 'organization.directorate_has_schools']);

        // Archiving a directorate that still has active schools is refused.
        $this->send('post', "/organization/directorates/{$directorateId}/archive")
            ->assertSessionHasErrors(['directorate' => 'organization.directorate_has_schools']);
    }

    #[Test]
    public function branch_and_department_statuses_follow_the_rules(): void
    {
        $this->send('post', '/organization/branches', ['name' => 'الصناعي', 'status' => 1])->assertSessionHasNoErrors();
        $this->send('post', '/organization/branches', ['name' => 'الزراعي', 'status' => 2])->assertSessionHasNoErrors();
        $industrial = $this->branchId('الصناعي');
        $agricultural = $this->branchId('الزراعي');
        $this->assertDatabaseHas(SchemaHelper::qualified('organization', 'branches'), ['id' => $agricultural, 'status' => 2]);

        // An inactive name is still taken; an archived one can be reused.
        $this->send('post', '/organization/branches', ['name' => 'الزراعي'])
            ->assertSessionHasErrors(['branch' => 'organization.branch_name_taken']);

        // Active department only in an active branch; an inactive one is allowed.
        $this->send('post', '/organization/departments', ['name' => 'إنتاج حيواني', 'branch_id' => $agricultural, 'status' => 1])
            ->assertSessionHasErrors(['department' => 'organization.branch_not_active']);
        $this->send('post', '/organization/departments', ['name' => 'إنتاج حيواني', 'branch_id' => $agricultural, 'status' => 2])
            ->assertSessionHasNoErrors();

        $this->send('post', '/organization/departments', ['name' => 'كهرباء', 'branch_id' => $industrial])->assertSessionHasNoErrors();
        $electricity = (int) DB::table(SchemaHelper::qualified('organization', 'departments'))
            ->where('branch_id', $industrial)->where('name', 'كهرباء')->value('id');

        // A branch with an active department stays active.
        $this->send('patch', "/organization/branches/{$industrial}", ['name' => 'الصناعي', 'status' => 2])
            ->assertSessionHasErrors(['branch' => 'organization.branch_has_departments']);

        // Department → inactive, then the branch may be archived; the name is free again.
        $this->send('patch', "/organization/departments/{$electricity}", ['name' => 'كهرباء', 'branch_id' => $industrial, 'status' => 2])
            ->assertSessionHasNoErrors();
        $this->send('patch', "/organization/branches/{$industrial}", ['name' => 'الصناعي', 'status' => 3])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas(SchemaHelper::qualified('organization', 'branches'), ['id' => $industrial, 'status' => 3]);
        $this->send('post', '/organization/branches', ['name' => 'الصناعي'])->assertSessionHasNoErrors();

        // The management page lists every status; the student form lists active ones only.
        $this->get('/organization/branches')->assertInertia(fn ($page) => $page
            ->where('branches', fn ($branches) => collect($branches)->pluck('status', 'id')->get($agricultural) === 2
                && collect($branches)->pluck('status', 'id')->get($industrial) === 3));
        $this->get('/students')->assertInertia(fn ($page) => $page
            ->where('schoolBranches', fn ($branches) => collect($branches)->pluck('name')->all() === ['الصناعي']));
    }

    #[Test]
    public function a_department_in_use_cannot_leave_active(): void
    {
        $this->send('post', '/organization/branches', ['name' => 'التجاري'])->assertSessionHasNoErrors();
        $commercial = $this->branchId('التجاري');
        $this->send('post', '/organization/departments', ['name' => 'محاسبة', 'branch_id' => $commercial])->assertSessionHasNoErrors();
        $accounting = (int) DB::table(SchemaHelper::qualified('organization', 'departments'))->where('name', 'محاسبة')->value('id');

        $yearId = $this->createAcademicYear('AY-STS');
        $class = $this->createClassForSchool($this->schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);
        $student = $this->createStudentForSchool($this->schoolId);
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $this->schoolId]);
        DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->insert([
            'student_id' => $student->id, 'academic_year_id' => $yearId, 'school_id' => $this->schoolId,
            'class_id' => $class->id, 'section_id' => $section->id, 'branch_id' => $commercial, 'department_id' => $accounting,
            'status' => 1, 'effective_from' => '2026-09-01', 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([2, 3] as $status) {
            $this->send('patch', "/organization/departments/{$accounting}", ['name' => 'محاسبة', 'branch_id' => $commercial, 'status' => $status])
                ->assertSessionHasErrors(['department' => 'organization.department_in_use']);
        }
        $this->send('post', '/organization/departments/delete', ['department_ids' => [$accounting]])
            ->assertSessionHasErrors(['department' => 'organization.department_in_use']);
        $this->assertDatabaseHas(SchemaHelper::qualified('organization', 'departments'), ['id' => $accounting, 'status' => 1]);
    }

    private function send(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->withHeader('X-Idempotency-Key', 'sts-'.bin2hex(random_bytes(6)))->{$method}($uri, $data);
    }

    private function branchId(string $name): int
    {
        return (int) DB::table(SchemaHelper::qualified('organization', 'branches'))
            ->where('school_id', $this->schoolId)->where('name', $name)->where('status', '<>', 3)->orderByDesc('id')->value('id');
    }
}
