<?php

namespace App\Application\Finance\Commands;

use App\Application\Contracts\Command;

final readonly class RecordPaymentCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $studentFeeId,
        public string $amount,
        public int $paymentMethod,
        public ?string $paymentReference = null,
        public ?string $paidAt = null,
        public ?int $receivedBy = null,
        public ?string $idempotencyKey = null,
    ) {}
}
