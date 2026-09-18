<?php

namespace App\Support\Ops;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\FoundationAcademicSeeder;
use Database\Seeders\FoundationOrganizationSeeder;
use Database\Seeders\SecurityPermissionSeeder;
use Database\Seeders\Support\FoundationReference;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Local/ops bootstrap: seed demo school + current academic year and bind the user.
 * Not a production entitlement path — gated by security.ops_bootstrap_enabled.
 */
final class OpsWorkspaceBootstrap
{
    /** @var list<string> */
    private const OPS_MANAGER_ROLES = [
        'student_manager',
        'enrollment_manager',
        'admission_manager',
        'grades_manager',
        'attendance_manager',
        'timetable_manager',
        'results_manager',
        'portal_scopes_manager',
        'teachers_manager',
        'promotion_manager',
        'transfers_manager',
        'documents_manager',
        'finance_manager',
        'communication_manager',
        'audit_manager',
        'workflow_manager',
        'hr_manager',
        'vocational_manager',
        'curriculum_manager',
    ];

    /**
     * @return array{school_id: int, academic_year_id: int, roles_assigned: int}
     */
    public function bootstrapFor(User $user): array
    {
        (new FoundationOrganizationSeeder)->run();
        (new FoundationAcademicSeeder)->run();

        $schoolId = $this->resolveSchoolId();
        $yearId = $this->resolveAcademicYearId();

        $this->localizeDemoLabels($schoolId, $yearId);

        $permissions = new SecurityPermissionSeeder;
        $permissions->run();

        $assigned = 0;
        foreach (self::OPS_MANAGER_ROLES as $roleCode) {
            $permissions->assignRole($user, $roleCode, $schoolId);
            $assigned++;
        }

        Cache::forget("security.schools.user.{$user->id}");
        Cache::forget("security.permissions.user.{$user->id}");

        return [
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'roles_assigned' => $assigned,
        ];
    }

    public function isNeeded(User $user): bool
    {
        $schoolsTable = SchemaHelper::qualified('organization', 'schools');
        $yearsTable = SchemaHelper::qualified('academic', 'academic_years');
        $userRolesTable = SchemaHelper::qualified('security', 'user_roles');

        try {
            if ((int) DB::table($schoolsTable)->count() === 0) {
                return true;
            }
            if ((int) DB::table($yearsTable)->count() === 0) {
                return true;
            }
            if ((int) DB::table($userRolesTable)->where('user_id', $user->id)->whereNotNull('school_id')->count() === 0) {
                return true;
            }
        } catch (\Throwable) {
            return true;
        }

        return false;
    }

    public function isEnabled(): bool
    {
        return (bool) config('security.ops_bootstrap_enabled', false);
    }

    private function resolveSchoolId(): int
    {
        $table = SchemaHelper::qualified('organization', 'schools');
        $id = (int) DB::table($table)->where('code', FoundationReference::SCHOOL_CODE)->value('id');
        if ($id < 1) {
            $id = (int) DB::table($table)->orderBy('id')->value('id');
        }
        if ($id < 1) {
            throw new \RuntimeException('Ops bootstrap could not resolve a school.');
        }

        return $id;
    }

    private function resolveAcademicYearId(): int
    {
        $table = SchemaHelper::qualified('academic', 'academic_years');
        $id = (int) DB::table($table)->where('code', FoundationReference::ACADEMIC_YEAR_CODE)->value('id');
        if ($id < 1) {
            $id = (int) DB::table($table)->where('is_current', true)->value('id');
        }
        if ($id < 1) {
            $id = (int) DB::table($table)->orderBy('id')->value('id');
        }
        if ($id < 1) {
            throw new \RuntimeException('Ops bootstrap could not resolve an academic year.');
        }

        return $id;
    }

    private function localizeDemoLabels(int $schoolId, int $yearId): void
    {
        DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('id', $schoolId)
            ->update([
                'name' => 'المدرسة التجريبية المهنية',
                'updated_at' => now(),
            ]);

        DB::table(SchemaHelper::qualified('academic', 'academic_years'))
            ->where('id', $yearId)
            ->update([
                'name' => 'السنة الدراسية 2026-2027',
                'is_current' => true,
                'updated_at' => now(),
            ]);
    }
}
