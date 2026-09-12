<?php

namespace App\Application\Finance\Queries;

final readonly class ListFeeTypesQuery
{
    public function __construct(
        public int $schoolId,
        public ?int $status = null,
    ) {}
}
