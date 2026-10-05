<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * «الفروع والاختصاصات»: branches and departments of the current school —
 * add / edit / delete (deactivate) with the rules of BranchStructureGuard.
 */
final class OrganizationBranchStructurePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    private int $schoolId;

    private int $otherSchoolId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->schoolId = $this->createSchool('SCH-BRS', 'اعدادية الرافدين المهنية');
        $this->otherSchoolId = $this->createSchool('SCH-BRO', 'اعدادية دجلة المهنية');

        $user = User::factory()->create();
        $seeder = app(SecurityPermissionSeeder::class);
        foreach ([$this->schoolId, $this->otherSchoolId] as $schoolId) {
            $seeder->grantStudentManager($user, $schoolId);
            $seeder->assignRole($user, 'admission_manager', $schoolId);
            $seeder->assignRole($user, 'school_manager', $schoolId);
        }
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $this->schoolId]);
    }

    #[Test]
    public function page_lists_the_schools_active_branches_with_their_departments(): void
    {
        $industrial = $this->createBranch('الصناعي', 'يختص بإعداد الكوادر الفنية');
        $this->createDepartment($industrial, 'الميكانيك');
        $this->createDepartment($industrial, 'الكهرباء');
        $this->createBranch('الزراعي');

        $this->get('/organization/branches')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('organization/branches')
                ->where('authorization.can_manage', true)
                ->where('branches', fn ($branches) => collect($branches)
                    ->mapWithKeys(fn ($b) => [$b['name'] => collect($b['departments'])->pluck('name')->all()])
                    ->all() === ['الصناعي' => ['الميكانيك', 'الكهرباء'], 'الزراعي' => []])
                ->where('branches.0.description', 'يختص بإعداد الكوادر الفنية'));

        // Another school's branches never show.
        $this->withSession(['current_school_id' => $this->otherSchoolId])
            ->get('/organization/branches')
            ->assertInertia(fn ($page) => $page->where('branches', []));
    }

    #[Test]
    public function branch_add_edit_delete_follow_the_rules(): void
    {
        $id = $this->createBranch('التجاري', 'المحاسبة والإدارة');
        $this->assertDatabaseHas(SchemaHelper::qualified('organization', 'branches'), [
            'id' => $id, 'school_id' => $this->schoolId, 'name' => 'التجاري', 'description' => 'المحاسبة والإدارة', 'status' => 1,
        ]);

        $this->send('post', '/organization/branches', ['name' => 'التجاري'])
            ->assertSessionHasErrors(['branch' => 'organization.branch_name_taken']);
        $this->send('post', '/organization/branches', ['name' => ''])->assertSessionHasErrors(['name']);
        // A request without idempotency key is refused.
        $this->withoutHeader('X-Idempotency-Key')
            ->post('/organization/branches', ['name' => 'بدون مفتاح'])
            ->assertSessionHasErrors(['X-Idempotency-Key']);

        $this->send('patch', "/organization/branches/{$id}", ['name' => 'التجاري والإداري', 'description' => null])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas(SchemaHelper::qualified('organization', 'branches'), ['id' => $id, 'name' => 'التجاري والإداري', 'description' => null]);

        $this->createDepartment($id, 'المحاسبة');
        $this->send('post', "/organization/branches/{$id}/delete")
            ->assertSessionHasErrors(['branch' => 'organization.branch_has_departments']);

        $empty = $this->createBranch('الفندقي والسياحي');
        $this->send('post', "/organization/branches/{$empty}/delete")->assertSessionHasNoErrors();
        $this->assertDatabaseHas(SchemaHelper::qualified('organization', 'branches'), ['id' => $empty, 'status' => 2]);
        // A deleted branch name can be reused.
        $this->createBranch('الفندقي والسياحي');

        // Another school's branch cannot be edited from this school.
        $foreign = $this->createBranch('الصناعي', null, $this->otherSchoolId);
        $this->send('patch', "/organization/branches/{$foreign}", ['name' => 'مخترق'])
            ->assertSessionHasErrors(['branch' => 'organization.branch_not_found']);
    }

    #[Test]
    public function department_add_edit_move_and_bulk_delete_follow_the_rules(): void
    {
        $industrial = $this->createBranch('الصناعي');
        $health = $this->createBranch('التمريض والصحي');
        $mechanics = $this->createDepartment($industrial, 'الميكانيك', 'صيانة المكائن');
        $electricity = $this->createDepartment($industrial, 'الكهرباء');

        $this->send('post', '/organization/departments', ['name' => 'الميكانيك', 'branch_id' => $industrial])
            ->assertSessionHasErrors(['department' => 'organization.department_name_taken']);
        $foreignBranch = $this->createBranch('الصناعي', null, $this->otherSchoolId);
        $this->send('post', '/organization/departments', ['name' => 'تبريد', 'branch_id' => $foreignBranch])
            ->assertSessionHasErrors(['department' => 'organization.branch_not_found']);

        // Move an unused department to another branch.
        $this->send('patch', "/organization/departments/{$electricity}", ['name' => 'الأجهزة الطبية', 'branch_id' => $health])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas(SchemaHelper::qualified('organization', 'departments'), [
            'id' => $electricity, 'branch_id' => $health, 'name' => 'الأجهزة الطبية',
        ]);

        // A department with an active enrollment is neither moved nor deleted.
        $this->enrollInDepartment($industrial, $mechanics);
        $this->send('patch', "/organization/departments/{$mechanics}", ['name' => 'الميكانيك', 'branch_id' => $health])
            ->assertSessionHasErrors(['department' => 'organization.department_in_use']);
        $this->send('post', '/organization/departments/delete', ['department_ids' => [$electricity, $mechanics]])
            ->assertSessionHasErrors(['department' => 'organization.department_in_use']);
        $this->assertDatabaseHas(SchemaHelper::qualified('organization', 'departments'), ['id' => $electricity, 'status' => 1]);
        // Renaming in place stays allowed.
        $this->send('patch', "/organization/departments/{$mechanics}", ['name' => 'ميكانيك السيارات', 'branch_id' => $industrial])
            ->assertSessionHasNoErrors();

        $this->send('post', '/organization/departments/delete', ['department_ids' => [$electricity]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas(SchemaHelper::qualified('organization', 'departments'), ['id' => $electricity, 'status' => 2]);
        // The used branch cannot be deleted either.
        $this->send('post', '/organization/departments/delete', ['department_ids' => []])->assertSessionHasErrors(['department_ids']);
    }

    #[Test]
    public function student_pages_list_only_the_current_schools_branches_for_the_form(): void
    {
        $industrial = $this->createBranch('الصناعي');
        $this->createDepartment($industrial, 'الميكانيك');
        $this->createBranch('الصناعي', null, $this->otherSchoolId);
        $student = $this->createStudentForSchool($this->schoolId);

        $this->get('/students')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('schoolBranches', fn ($branches) => collect($branches)
                    ->map(fn ($b) => [$b['name'], collect($b['departments'])->pluck('name')->all()])
                    ->all() === [['الصناعي', ['الميكانيك']]]));
        $this->get("/students/{$student->id}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('schoolBranches.0.name', 'الصناعي'));
    }

    #[Test]
    public function viewer_without_school_management_cannot_write(): void
    {
        $viewer = User::factory()->create();
        $seeder = app(SecurityPermissionSeeder::class);
        $seeder->grantStudentManager($viewer, $this->schoolId);
        $seeder->assignRole($viewer, 'admission_manager', $this->schoolId);
        $this->actingAs($viewer);

        $this->get('/organization/branches')->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('authorization.can_manage', false));
        $this->send('post', '/organization/branches', ['name' => 'فرع غير مسموح'])->assertForbidden();
    }

    private function send(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->withHeader('X-Idempotency-Key', 'brs-'.bin2hex(random_bytes(6)))->{$method}($uri, $data);
    }

    private function createBranch(string $name, ?string $description = null, ?int $schoolId = null): int
    {
        if ($schoolId !== null) {
            return (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
                'school_id' => $schoolId, 'code' => 'BRX-'.bin2hex(random_bytes(3)), 'name' => $name, 'status' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->send('post', '/organization/branches', ['name' => $name, 'description' => $description])->assertSessionHasNoErrors();

        return (int) DB::table(SchemaHelper::qualified('organization', 'branches'))
            ->where('school_id', $this->schoolId)->where('name', $name)->where('status', 1)->value('id');
    }

    private function createDepartment(int $branchId, string $name, ?string $description = null): int
    {
        $this->send('post', '/organization/departments', ['name' => $name, 'description' => $description, 'branch_id' => $branchId])
            ->assertSessionHasNoErrors();

        return (int) DB::table(SchemaHelper::qualified('organization', 'departments'))
            ->where('branch_id', $branchId)->where('name', $name)->where('status', 1)->value('id');
    }

    private function enrollInDepartment(int $branchId, int $departmentId): void
    {
        $yearId = $this->createAcademicYear('AY-BRS');
        $class = $this->createClassForSchool($this->schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);
        $student = $this->createStudentForSchool($this->schoolId);
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $this->schoolId]);
        DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->insert([
            'student_id' => $student->id, 'academic_year_id' => $yearId, 'school_id' => $this->schoolId,
            'class_id' => $class->id, 'section_id' => $section->id, 'branch_id' => $branchId, 'department_id' => $departmentId,
            'status' => 1, 'effective_from' => '2026-09-01', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
