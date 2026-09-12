<?php

namespace App\Domain\Workflow\Events;

use App\Domain\Shared\DomainEvent;
use App\Domain\Workflow\ValueObjects\ApprovalRequestStatus;

final readonly class ApprovalRequestCancelled implements DomainEvent
{
    public function __construct(
        private int $requestId,
        private int $schoolId,
        private int $previousStatus,
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
            'request_id' => $this->requestId,
            'school_id' => $this->schoolId,
            'previous_status' => $this->previousStatus,
            'status' => ApprovalRequestStatus::Cancelled,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
