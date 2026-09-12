<?php

namespace App\Application\Finance\Queries;

final readonly class ListPaymentsQuery
{
    public function __construct(
        public int $schoolId,
        public ?int $studentFeeId = null,
    ) {}
}
