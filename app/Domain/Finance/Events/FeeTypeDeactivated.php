<?php

namespace App\Domain\Finance\Events;

use App\Domain\Shared\DomainEvent;

final readonly class FeeTypeDeactivated implements DomainEvent
{
    public function __construct(
        private int $feeTypeId,
        private int $schoolId,
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
            'fee_type_id' => $this->feeTypeId,
            'school_id' => $this->schoolId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
