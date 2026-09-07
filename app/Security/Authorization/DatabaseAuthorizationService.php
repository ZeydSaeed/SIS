<?php

namespace App\Security\Authorization;

use App\Database\SchemaHelper;
use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class DatabaseAuthorizationService implements AuthorizationServiceInterface
{
    public function userHasPermission(User $user, string $permissionCode): bool
    {
        return in_array($permissionCode, $this->permissionsFor($user), true);
    }

    /**
     * @return list<string>
     */
    public function permissionsFor(User $user): array
    {
        return Cache::remember(
            "security.permissions.user.{$user->id}",
            now()->addMinutes(5),
            fn (): array => $this->loadPermissions($user),
        );
    }

    /**
     * @return list<string>
     */
    private function loadPermissions(User $user): array
    {
        $rolesTable = SchemaHelper::qualified('security', 'roles');
        $permissionsTable = SchemaHelper::qualified('security', 'permissions');
        $rolePermissionsTable = SchemaHelper::qualified('security', 'role_permissions');
        $userRolesTable = SchemaHelper::qualified('security', 'user_roles');

        if (! $this->tablesExist([$rolesTable, $permissionsTable, $rolePermissionsTable, $userRolesTable])) {
            return [];
        }

        $today = now()->toDateString();

        /** @var list<string> */
        return DB::table($permissionsTable.' as p')
            ->join($rolePermissionsTable.' as rp', 'rp.permission_id', '=', 'p.id')
            ->join($userRolesTable.' as ur', 'ur.role_id', '=', 'rp.role_id')
            ->where('ur.user_id', $user->id)
            ->where('ur.effective_from', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('ur.effective_to')
                    ->orWhere('ur.effective_to', '>=', $today);
            })
            ->distinct()
            ->orderBy('p.code')
            ->pluck('p.code')
            ->all();
    }

    /**
     * @param  list<string>  $tables
     */
    private function tablesExist(array $tables): bool
    {
        foreach ($tables as $table) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                return false;
            }
        }

        return true;
    }
}
