<?php

namespace App\Infrastructure\Organization;

use App\Application\Organization\Contracts\SchoolMembershipPort;
use App\Database\SchemaHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class EloquentSchoolMembershipAdapter implements SchoolMembershipPort
{
    public function grantSameRoles(int $userId, int $fromSchoolId, int $toSchoolId, string $effectiveFrom): int
    {
        $table = SchemaHelper::qualified('security', 'user_roles');

        /** @var list<int|string> $roleIds */
        $roleIds = DB::table($table)
            ->where('user_id', $userId)
            ->where('school_id', $fromSchoolId)
            ->where('effective_from', '<=', $effectiveFrom)
            ->where(function ($query) use ($effectiveFrom): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $effectiveFrom);
            })
            ->distinct()
            ->pluck('role_id')
            ->all();

        $granted = 0;
        foreach ($roleIds as $roleId) {
            DB::table($table)->insert([
                'user_id' => $userId,
                'role_id' => (int) $roleId,
                'school_id' => $toSchoolId,
                'directorate_id' => null,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
            ]);
            $granted++;
        }

        Cache::forget("security.schools.user.{$userId}");

        return $granted;
    }
}
