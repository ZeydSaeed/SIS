<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Domain\Leave\Repositories\LeaveNotificationRepositoryInterface;

/** «الإشعارات»: the user's own notifications, newest first (ids, kinds and the names of the people involved only). */
final class ListLeaveNotificationsHandler implements QueryHandler
{
    public function __construct(
        private readonly LeaveNotificationRepositoryInterface $notifications,
    ) {}

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function handle(Query $query): array
    {
        assert($query instanceof ListLeaveNotificationsQuery);

        return $this->notifications->list($query->schoolId, $query->userId, $query->unreadOnly, $query->page, $query->perPage);
    }
}
