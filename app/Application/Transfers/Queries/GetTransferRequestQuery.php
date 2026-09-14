<?php

namespace App\Application\Transfers\Queries;

final readonly class GetTransferRequestQuery
{
    public function __construct(
        public int $schoolId,
        public int $transferRequestId,
    ) {}
}
