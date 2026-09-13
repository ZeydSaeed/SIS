<?php

namespace App\Domain\Finance\Data;

final readonly class PaymentSnapshot
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $studentFeeId,
        public string $amount,
        public int $paymentMethod,
        public ?string $paymentReference,
        public string $idempotencyKey,
        public string $paidAt,
        public ?int $receivedBy,
        public int $status,
        public ?string $voidedAt,
        public ?int $voidedBy,
        public string $createdAt,
    ) {}
}
