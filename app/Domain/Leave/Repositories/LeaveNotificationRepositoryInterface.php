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

    /** Users holding an effective role with `leave.request.approve` in the school. @return list<int> */
    public function approverUserIds(int $schoolId): array;
}
