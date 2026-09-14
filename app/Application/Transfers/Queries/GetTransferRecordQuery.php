<?php

namespace App\Application\Transfers\Queries;

final readonly class GetTransferRecordQuery
{
    public function __construct(
        public int $schoolId,
        public int $recordId,
    ) {}
}
