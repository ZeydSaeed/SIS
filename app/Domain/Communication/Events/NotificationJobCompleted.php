<?php

namespace App\Domain\Communication\Events;

use App\Domain\Shared\DomainEvent;

final readonly class NotificationJobCompleted implements DomainEvent
{
    public function __construct(
        private int $notificationJobId,
        private int $schoolId,
        private int $sentCount,
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
            'notification_job_id' => $this->notificationJobId,
            'school_id' => $this->schoolId,
            'sent_count' => $this->sentCount,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
