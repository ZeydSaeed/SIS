<?php

namespace App\Domain\Admission\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ApplicationDraftCreated implements DomainEvent
{
    public function __construct(
        private int $applicationId,
        private int $periodId,
        private int $schoolId,
        private string $applicationNumber,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'application_id' => $this->applicationId,
            'period_id' => $this->periodId,
            'school_id' => $this->schoolId,
            'application_number' => $this->applicationNumber,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
