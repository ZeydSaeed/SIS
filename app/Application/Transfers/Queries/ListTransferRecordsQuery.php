<?php

namespace App\Application\Transfers\Queries;

final readonly class ListTransferRecordsQuery
{
    public function __construct(
        public int $schoolId,
    ) {}
}
