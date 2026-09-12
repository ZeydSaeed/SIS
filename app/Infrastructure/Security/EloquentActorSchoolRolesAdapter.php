<?php

namespace App\Infrastructure\Security;

use App\Application\Workflow\Contracts\ActorSchoolRolesPort;
use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;

final class EloquentActorSchoolRolesAdapter implements ActorSchoolRolesPort
{
    public function roleCodesForUserInSchool(int $userId, int $schoolId): array
    {
        $userRoles = SchemaHelper::qualified('security', 'user_roles');
        $roles = SchemaHelper::qualified('security', 'roles');

        $rows = DB::table($userRoles.' as ur')
            ->join($roles.' as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('ur.school_id', $schoolId)
            ->where(function ($q): void {
                $q->whereNull('ur.effective_to')
                    ->orWhere('ur.effective_to', '>=', now()->toDateString());
            })
            ->orderBy('r.code')
            ->pluck('r.code')
            ->all();

        return array_values(array_unique(array_map('strval', $rows)));
    }
}
