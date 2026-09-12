<?php

namespace App\Application\Portal\Queries;

final readonly class ListPortalPartyScopesQuery
{
    public function __construct(
        public int $userId,
    ) {}
}
