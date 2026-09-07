<?php

namespace Database\Seeders;

use App\Database\SchemaHelper;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SecurityPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $rolesTable = SchemaHelper::qualified('security', 'roles');
        $permissionsTable = SchemaHelper::qualified('security', 'permissions');
        $rolePermissionsTable = SchemaHelper::qualified('security', 'role_permissions');

        foreach (config('security.permissions', []) as $code => $name) {
            DB::table($permissionsTable)->updateOrInsert(
                ['code' => $code],
                ['name' => $name, 'module' => explode('.', $code)[0] ?? 'core'],
            );
        }

        foreach (config('security.roles', []) as $roleCode => $permissionCodes) {
            DB::table($rolesTable)->updateOrInsert(
                ['code' => $roleCode],
                ['name' => str_replace('_', ' ', ucfirst($roleCode)), 'description' => null, 'is_system' => true],
            );

            $roleId = (int) DB::table($rolesTable)->where('code', $roleCode)->value('id');

            foreach ($permissionCodes as $permissionCode) {
                $permissionId = (int) DB::table($permissionsTable)->where('code', $permissionCode)->value('id');
                if ($permissionId === 0) {
                    continue;
                }

                DB::table($rolePermissionsTable)->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                );
            }
        }
    }

    public function assignRole(User $user, string $roleCode, ?int $schoolId = null): void
    {
        $rolesTable = SchemaHelper::qualified('security', 'roles');
        $userRolesTable = SchemaHelper::qualified('security', 'user_roles');

        $roleId = (int) DB::table($rolesTable)->where('code', $roleCode)->value('id');
        if ($roleId === 0) {
            return;
        }

        DB::table($userRolesTable)->updateOrInsert(
            ['user_id' => $user->id, 'role_id' => $roleId, 'school_id' => $schoolId],
            [
                'user_id' => $user->id,
                'role_id' => $roleId,
                'school_id' => $schoolId,
                'directorate_id' => null,
                'effective_from' => now()->toDateString(),
                'effective_to' => null,
            ],
        );
    }

    public function grantStudentManager(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'student_manager', $schoolId);
    }

    public function grantStudentViewer(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'student_viewer', $schoolId);
    }
}
