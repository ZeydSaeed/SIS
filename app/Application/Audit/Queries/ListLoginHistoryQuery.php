<?php

namespace App\Application\Audit\Queries;

final readonly class ListLoginHistoryQuery
{
    public function __construct(
        public int $schoolId,
        public ?int $userId = null,
        public int $limit = 50,
    ) {}
}
