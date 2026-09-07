<?php

namespace App\Security\Authorization;

use App\Database\SchemaHelper;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class SchoolScopeService
{
    /** @var array<int, list<int>> */
    private array $requestMemo = [];

    /**
     * @return list<int>
     */
    public function allowedSchoolIds(User $user): array
    {
        if (isset($this->requestMemo[$user->id])) {
            return $this->requestMemo[$user->id];
        }

        return $this->requestMemo[$user->id] = Cache::remember(
            "security.schools.user.{$user->id}",
            now()->addMinutes(5),
            fn (): array => $this->loadAllowedSchoolIds($user),
        );
    }

    /**
     * @return list<int>
     */
    private function loadAllowedSchoolIds(User $user): array
    {
        $userRolesTable = SchemaHelper::qualified('security', 'user_roles');

        if (! DB::getSchemaBuilder()->hasTable($userRolesTable)) {
            return [];
        }

        $today = now()->toDateString();

        /** @var list<int|string|null> $schoolIds */
        $schoolIds = DB::table($userRolesTable)
            ->where('user_id', $user->id)
            ->where('effective_from', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $today);
            })
            ->whereNotNull('school_id')
            ->distinct()
            ->pluck('school_id')
            ->all();

        return array_values(array_map(
            fn (int|string $id): int => (int) $id,
            array_filter($schoolIds, fn ($id): bool => $id !== null),
        ));
    }
}
