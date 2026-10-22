<?php

namespace App\Infrastructure\Persistence\Leave;

use App\Domain\Leave\Repositories\LeaveNotificationRepositoryInterface;
use App\Domain\Leave\ValueObjects\LeaveNotificationKind;
use App\Security\Authorization\Permission;
use Illuminate\Support\Facades\DB;

final class EloquentLeaveNotificationRepository implements LeaveNotificationRepositoryInterface
{
    public function add(int $schoolId, int $userId, int $requestId, LeaveNotificationKind $kind, ?int $actorUserId): void
    {
        // One unread «ينتظر موافقتك» per approver and request — a second round does not stack another.
        if (in_array($kind, [LeaveNotificationKind::AwaitingApproval, LeaveNotificationKind::HolidayChanged], true)
            && DB::table('leave.notifications')->where('user_id', $userId)->where('request_id', $requestId)->where('kind', $kind->value)->whereNull('read_at')->exists()) {
            return;
        }
        DB::table('leave.notifications')->insert([
            'school_id' => $schoolId,
            'user_id' => $userId,
            'request_id' => $requestId,
            'kind' => $kind->value,
            'actor_user_id' => $actorUserId,
            // The application's clock, like submitted_at that «once per round» compares it with: the column default
            // NOW() is the transaction's start, which can precede the submission it answers.
            'created_at' => now(),
        ]);
    }

    public function unreadCount(int $schoolId, int $userId): int
    {
        return DB::table('leave.notifications')->where('school_id', $schoolId)->where('user_id', $userId)->whereNull('read_at')->count();
    }

    public function list(int $schoolId, int $userId, bool $unreadOnly, int $page, int $perPage): array
    {
        $query = DB::table('leave.notifications as n')
            ->join('leave.requests as r', 'r.id', '=', 'n.request_id')
            ->leftJoin('public.users as a', 'a.id', '=', 'n.actor_user_id')
            ->leftJoin('teachers.teachers as t', 't.id', '=', 'r.teacher_id')
            ->leftJoin('hr.employees as e', 'e.id', '=', 'r.employee_id')
            ->where('n.school_id', $schoolId)->where('n.user_id', $userId)
            ->when($unreadOnly, static fn ($q) => $q->whereNull('n.read_at'));
        $total = (clone $query)->count();
        $items = $query->orderByDesc('n.id')->forPage(max(1, $page), $perPage)->get([
            'n.id', 'n.request_id', 'n.kind', 'n.created_at', 'n.read_at', 'r.request_no', 'r.status', 'r.date_from', 'r.date_to',
            'a.name as actor_name', DB::raw('COALESCE(t.full_name, e.full_name) AS person_name'),
        ])->map(static fn (object $n): array => [
            'id' => (int) $n->id,
            'request_id' => (int) $n->request_id,
            'request_no' => (int) $n->request_no,
            'kind' => (int) $n->kind,
            'status' => (int) $n->status,
            'person_name' => $n->person_name,
            'actor_name' => $n->actor_name,
            'date_from' => substr((string) $n->date_from, 0, 10),
            'date_to' => substr((string) $n->date_to, 0, 10),
            'created_at' => (string) $n->created_at,
            'read' => $n->read_at !== null,
        ])->all();

        return ['items' => $items, 'total' => $total];
    }

    public function markRead(int $schoolId, int $userId, ?array $ids, string $at): int
    {
        return DB::table('leave.notifications')
            ->where('school_id', $schoolId)->where('user_id', $userId)->whereNull('read_at')
            ->when($ids !== null, static fn ($q) => $q->whereIn('id', $ids ?? []))
            ->update(['read_at' => $at]);
    }

    public function approverUserIds(int $schoolId): array
    {
        $holders = $this->userIdsWithPermission($schoolId, Permission::LEAVE_REQUEST_APPROVE);
        $today = now()->toDateString();
        // Whoever stands in today for one of them is asked too (the delegation only counts while its delegator still holds the right).
        $delegates = $holders === [] ? [] : DB::table('leave.delegations')
            ->where('school_id', $schoolId)->where('status', 1)->where('date_from', '<=', $today)->where('date_to', '>=', $today)
            ->whereIn('delegator_user_id', $holders)
            ->pluck('delegate_user_id')->map(static fn ($id): int => (int) $id)->all();

        return array_values(array_unique([...$holders, ...$delegates]));
    }

    public function managerUserIds(int $schoolId): array
    {
        return $this->userIdsWithPermission($schoolId, Permission::LEAVE_REQUEST_MANAGE);
    }

    private function userIdsWithPermission(int $schoolId, string $permission): array
    {
        $today = now()->toDateString();

        return DB::table('security.user_roles as ur')
            ->join('security.role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')
            ->join('security.permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('p.code', $permission)
            ->where('ur.school_id', $schoolId)
            ->where('ur.effective_from', '<=', $today)
            ->where(static fn ($q) => $q->whereNull('ur.effective_to')->orWhere('ur.effective_to', '>=', $today))
            ->distinct()
            ->pluck('ur.user_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    public function exists(int $userId, int $requestId, LeaveNotificationKind $kind, string $since): bool
    {
        return DB::table('leave.notifications')->where('user_id', $userId)->where('request_id', $requestId)->where('kind', $kind->value)->where('created_at', '>=', $since)->exists();
    }
}
