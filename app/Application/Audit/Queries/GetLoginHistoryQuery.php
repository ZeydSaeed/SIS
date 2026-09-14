<?php

namespace App\Application\Audit\Queries;

final readonly class GetLoginHistoryQuery
{
    public function __construct(
        public int $schoolId,
        public int $entryId,
    ) {}
}
