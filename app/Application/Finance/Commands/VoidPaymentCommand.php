<?php

namespace App\Application\Finance\Commands;

use App\Application\Contracts\Command;

final readonly class VoidPaymentCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $paymentId,
        public ?int $voidedBy,
        public ?string $idempotencyKey,
        public ?string $notes = null,
    ) {}
}
