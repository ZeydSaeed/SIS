<?php

namespace App\Domain\Communication\Events;

use App\Domain\Shared\DomainEvent;

final readonly class MessageSent implements DomainEvent
{
    public function __construct(
        private int $messageId,
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
            'message_id' => $this->messageId,
            'school_id' => $this->schoolId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
