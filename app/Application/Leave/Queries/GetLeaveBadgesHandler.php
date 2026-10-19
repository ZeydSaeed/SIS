<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Leave\Contracts\LeaveReadRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveNotificationRepositoryInterface;

/** The two counters on every leave page: unread notifications, and requests awaiting this user's decision. */
final class GetLeaveBadgesHandler implements QueryHandler
{
    public function __construct(
        private readonly LeaveNotificationRepositoryInterface $notifications,
        private readonly LeaveReadRepositoryInterface $read,
    ) {}

    /** @return array{unread: int, awaiting: int} */
    public function handle(Query $query): array
    {
        assert($query instanceof GetLeaveBadgesQuery);

        return [
            'unread' => $this->notifications->unreadCount($query->schoolId, $query->userId),
            'awaiting' => $query->canApprove ? $this->read->awaitingCount($query->schoolId, $query->userId) : 0,
        ];
    }
}
