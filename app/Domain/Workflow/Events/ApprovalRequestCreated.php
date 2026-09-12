<?php

namespace App\Domain\Workflow\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ApprovalRequestCreated implements DomainEvent
{
    public function __construct(
        private int $requestId,
        private int $schoolId,
        private int $flowId,
        private string $entityType,
        private int $entityId,
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
            'flow_id' => $this->flowId,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
