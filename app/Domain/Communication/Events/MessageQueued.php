<?php

namespace App\Domain\Communication\Events;

use App\Domain\Shared\DomainEvent;

final readonly class MessageQueued implements DomainEvent
{
    public function __construct(
        private int $messageId,
        private int $schoolId,
        private string $recipientType,
        private int $recipientId,
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
            'recipient_type' => $this->recipientType,
            'recipient_id' => $this->recipientId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
