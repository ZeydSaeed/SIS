<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;

/** `$delegatorUserId` null = the whole school (leave managers); otherwise only that approver's own delegations. */
final readonly class ListLeaveDelegationsQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $userId,
        public ?int $delegatorUserId,
    ) {}
}
