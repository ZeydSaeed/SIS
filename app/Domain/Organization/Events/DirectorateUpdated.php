<?php

namespace App\Domain\Organization\Events;

use App\Domain\Shared\DomainEvent;

final readonly class DirectorateUpdated implements DomainEvent
{
    public function __construct(
        private int $directorateId,
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
            'directorate_id' => $this->directorateId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
