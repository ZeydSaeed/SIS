<?php

namespace App\Domain\Finance\ValueObjects;

final class FinanceTransactionType
{
    public const FeeAssigned = 1;

    public const PaymentReceived = 2;
}
