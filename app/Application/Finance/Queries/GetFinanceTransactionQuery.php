<?php

namespace App\Application\Finance\Queries;

final readonly class GetFinanceTransactionQuery
{
    public function __construct(
        public int $schoolId,
        public int $transactionId,
    ) {}
}
