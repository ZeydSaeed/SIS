<?php

namespace App\Application\Finance\Queries;

final readonly class GetFeeTypeQuery
{
    public function __construct(
        public int $schoolId,
        public int $feeTypeId,
    ) {}
}
