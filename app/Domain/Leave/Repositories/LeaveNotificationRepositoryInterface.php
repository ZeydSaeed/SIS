<?php

namespace App\Domain\Leave\Repositories;

use App\Domain\Leave\ValueObjects\LeaveNotificationKind;

/** leave.notifications — one row per recipient; only `read_at` ever changes. */
interface LeaveNotificationRepositoryInterface
{
    public function add(int $schoolId, int $userId, int $requestId, LeaveNotificationKind $kind, ?int $actorUserId): void;

    public function unreadCount(int $schoolId, int $userId): int;

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function list(int $schoolId, int $userId, bool $unreadOnly, int $page, int $perPage): array;

    /**
     * Marks the user's unread notifications read — the given ids, or all of them when `$ids` is null.
     *
     * @param  list<int>|null  $ids
     * @return int rows marked
     */
    public function markRead(int $schoolId, int $userId, ?array $ids, string $at): int;

    /** Users who may decide leave in the school today: holders of `leave.request.approve` and their active delegates. @return list<int> */
    public function approverUserIds(int $schoolId): array;

    /** Users managing the school's leave (`leave.request.manage`): told about holiday changes and escalations. @return list<int> */
    public function managerUserIds(int $schoolId): array;

    /** Whether the user got a notification of this kind about the request at or after `$since` (a reminder goes out once per round). */
    public function exists(int $userId, int $requestId, LeaveNotificationKind $kind, string $since): bool;
}
