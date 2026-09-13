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

    public function grantEnrollmentManager(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'enrollment_manager', $schoolId);
    }

    public function grantEnrollmentViewer(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'enrollment_viewer', $schoolId);
    }

    public function grantGradesManager(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'grades_manager', $schoolId);
    }

    public function grantGradesTeacher(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'grades_teacher', $schoolId);
    }

    public function grantGradesViewer(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'grades_viewer', $schoolId);
    }

    public function grantAttendanceViewer(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'attendance_viewer', $schoolId);
    }

    public function grantAttendanceTeacher(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'attendance_teacher', $schoolId);
    }

    public function grantAttendanceManager(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'attendance_manager', $schoolId);
    }

    public function grantTimetableManager(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'timetable_manager', $schoolId);
    }

    public function grantResultsViewer(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'results_viewer', $schoolId);
    }

    public function grantResultsManager(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'results_manager', $schoolId);
    }

    public function grantPortalResultsViewer(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'portal_results_viewer', $schoolId);
    }

    public function grantPortalScopesManager(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'portal_scopes_manager', $schoolId);
    }

    public function grantTeachersManager(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'teachers_manager', $schoolId);
    }

    public function grantTeachersViewer(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'teachers_viewer', $schoolId);
    }

    public function grantPromotionManager(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'promotion_manager', $schoolId);
    }

    public function grantPromotionViewer(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'promotion_viewer', $schoolId);
    }

    public function grantTransfersManager(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'transfers_manager', $schoolId);
    }

    public function grantTransfersViewer(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'transfers_viewer', $schoolId);
    }

    public function grantDocumentsManager(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'documents_manager', $schoolId);
    }

    public function grantDocumentsViewer(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'documents_viewer', $schoolId);
    }

    public function grantFinanceManager(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'finance_manager', $schoolId);
    }

    public function grantFinanceViewer(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'finance_viewer', $schoolId);
    }

    public function grantCommunicationManager(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'communication_manager', $schoolId);
    }

    public function grantCommunicationViewer(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'communication_viewer', $schoolId);
    }

    public function grantWorkflowManager(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'workflow_manager', $schoolId);
    }

    public function grantWorkflowViewer(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'workflow_viewer', $schoolId);
    }

    public function grantHrManager(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'hr_manager', $schoolId);
    }

    public function grantHrViewer(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'hr_viewer', $schoolId);
    }

    public function grantVocationalManager(User $user, ?int $schoolId = null): void
    {
        $this->run();
        $this->assignRole($user, 'vocational_manager', $schoolId);
    }
}
