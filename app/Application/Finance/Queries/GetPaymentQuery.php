<?php

namespace App\Application\Finance\Queries;

final readonly class GetPaymentQuery
{
    public function __construct(
        public int $schoolId,
        public int $paymentId,
    ) {}
}
