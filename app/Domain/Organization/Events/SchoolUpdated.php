<?php

namespace App\Domain\Organization\Events;

use App\Domain\Shared\DomainEvent;

final readonly class SchoolUpdated implements DomainEvent
{
    public function __construct(
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
            'school_id' => $this->schoolId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
