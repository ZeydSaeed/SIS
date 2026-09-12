<?php

namespace App\Domain\Workflow\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ApprovalRequestDecided implements DomainEvent
{
    public function __construct(
        private int $requestId,
        private int $schoolId,
        private string $decision,
        private int $status,
        private int $currentStep,
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
            'decision' => $this->decision,
            'status' => $this->status,
            'current_step' => $this->currentStep,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
