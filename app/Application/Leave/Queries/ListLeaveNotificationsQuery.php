<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;

final readonly class ListLeaveNotificationsQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $userId,
        public bool $unreadOnly,
        public int $page,
        public int $perPage,
    ) {}
}
