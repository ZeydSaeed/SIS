<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;

final readonly class GetLeaveBadgesQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $userId,
        public bool $canApprove,
    ) {}
}
