<?php

namespace App\Domain\Workflow\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ApprovalFlowDeactivated implements DomainEvent
{
    public function __construct(
        private int $flowId,
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
            'flow_id' => $this->flowId,
            'school_id' => $this->schoolId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
