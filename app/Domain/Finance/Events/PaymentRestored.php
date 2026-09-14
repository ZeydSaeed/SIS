<?php

namespace App\Domain\Finance\Events;

use App\Domain\Shared\DomainEvent;

final readonly class PaymentRestored implements DomainEvent
{
    public function __construct(
        private int $paymentId,
        private int $schoolId,
        private int $studentFeeId,
        private string $amount,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'school_id' => $this->schoolId,
            'student_fee_id' => $this->studentFeeId,
            'amount' => $this->amount,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
