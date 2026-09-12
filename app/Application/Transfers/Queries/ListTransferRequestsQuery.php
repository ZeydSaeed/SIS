<?php

namespace App\Application\Transfers\Queries;

final readonly class ListTransferRequestsQuery
{
    public function __construct(
        public int $schoolId,
        public ?int $academicYearId = null,
        public ?int $status = null,
    ) {}
}
