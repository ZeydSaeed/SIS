<?php

namespace App\Infrastructure\Persistence\Leave;

use App\Domain\Leave\Repositories\LeaveDelegationRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentLeaveDelegationRepository implements LeaveDelegationRepositoryInterface
{
    public function activeDelegatorIds(int $schoolId, int $delegateUserId): array
    {
        $today = now()->toDateString();

        return DB::table('leave.delegations')
            ->where('school_id', $schoolId)->where('delegate_user_id', $delegateUserId)->where('status', self::ACTIVE)
            ->where('date_from', '<=', $today)->where('date_to', '>=', $today)
            ->pluck('delegator_user_id')->map(static fn ($id): int => (int) $id)->unique()->values()->all();
    }

    public function activePairs(int $schoolId): array
    {
        $today = now()->toDateString();

        return DB::table('leave.delegations')
            ->where('school_id', $schoolId)->where('status', self::ACTIVE)
            ->where('date_from', '<=', $today)->where('date_to', '>=', $today)
            ->get(['delegator_user_id', 'delegate_user_id'])
            ->map(static fn (object $r): array => ['delegator' => (int) $r->delegator_user_id, 'delegate' => (int) $r->delegate_user_id])
            ->all();
    }

    public function list(int $schoolId, ?int $delegatorUserId): array
    {
        return DB::table('leave.delegations as d')
            ->join('public.users as a', 'a.id', '=', 'd.delegator_user_id')
            ->join('public.users as b', 'b.id', '=', 'd.delegate_user_id')
            ->where('d.school_id', $schoolId)
            ->when($delegatorUserId !== null, static fn ($q) => $q->where('d.delegator_user_id', $delegatorUserId))
            ->orderByDesc('d.id')->limit(200)
            ->get(['d.id', 'd.delegator_user_id', 'd.delegate_user_id', 'd.date_from', 'd.date_to', 'd.status', 'd.created_at', 'd.revoked_at', 'a.name as delegator_name', 'b.name as delegate_name'])
            ->map(static fn (object $r): array => [
                'id' => (int) $r->id,
                'delegator_user_id' => (int) $r->delegator_user_id,
                'delegator_name' => (string) $r->delegator_name,
                'delegate_user_id' => (int) $r->delegate_user_id,
                'delegate_name' => (string) $r->delegate_name,
                'date_from' => substr((string) $r->date_from, 0, 10),
                'date_to' => substr((string) $r->date_to, 0, 10),
                'status' => (int) $r->status,
                'created_at' => (string) $r->created_at,
                'revoked_at' => $r->revoked_at === null ? null : (string) $r->revoked_at,
            ])->all();
    }

    public function find(int $schoolId, int $delegationId): ?array
    {
        $row = DB::table('leave.delegations')->where('school_id', $schoolId)->where('id', $delegationId)->first();

        return $row === null ? null : [
            'id' => (int) $row->id,
            'delegator_user_id' => (int) $row->delegator_user_id,
            'delegate_user_id' => (int) $row->delegate_user_id,
            'status' => (int) $row->status,
        ];
    }

    public function overlapsActive(int $schoolId, int $delegatorUserId, int $delegateUserId, string $from, string $to): bool
    {
        return DB::table('leave.delegations')
            ->where('school_id', $schoolId)->where('delegator_user_id', $delegatorUserId)->where('delegate_user_id', $delegateUserId)
            ->where('status', self::ACTIVE)->where('date_from', '<=', $to)->where('date_to', '>=', $from)
            ->exists();
    }

    public function create(int $schoolId, int $delegatorUserId, int $delegateUserId, string $from, string $to, int $createdBy): int
    {
        return (int) DB::table('leave.delegations')->insertGetId([
            'school_id' => $schoolId,
            'delegator_user_id' => $delegatorUserId,
            'delegate_user_id' => $delegateUserId,
            'date_from' => $from,
            'date_to' => $to,
            'created_by' => $createdBy,
        ]);
    }

    public function revoke(int $schoolId, int $delegationId, int $revokedBy): bool
    {
        return DB::table('leave.delegations')
            ->where('school_id', $schoolId)->where('id', $delegationId)->where('status', self::ACTIVE)
            ->update(['status' => self::REVOKED, 'revoked_by' => $revokedBy, 'revoked_at' => now()]) === 1;
    }

    public function hasRoleInSchool(int $schoolId, int $userId): bool
    {
        return $this->roleQuery($schoolId)->where('ur.user_id', $userId)->exists();
    }

    public function candidates(int $schoolId, int $exceptUserId): array
    {
        return $this->roleQuery($schoolId)
            ->join('public.users as u', 'u.id', '=', 'ur.user_id')
            ->where('ur.user_id', '<>', $exceptUserId)
            ->distinct()->orderBy('u.name')->limit(500)
            ->get(['u.id', 'u.name'])
            ->map(static fn (object $u): array => ['id' => (int) $u->id, 'name' => (string) $u->name])
            ->all();
    }

    private function roleQuery(int $schoolId): Builder
    {
        $today = now()->toDateString();

        return DB::table('security.user_roles as ur')
            ->where('ur.school_id', $schoolId)->where('ur.effective_from', '<=', $today)
            ->where(static fn ($q) => $q->whereNull('ur.effective_to')->orWhere('ur.effective_to', '>=', $today));
    }
}
