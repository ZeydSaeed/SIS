<?php

namespace App\Domain\Admission\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ApplicationStatusTransitioned implements DomainEvent
{
    public function __construct(
        private int $applicationId,
        private int $schoolId,
        private int $fromStatus,
        private int $toStatus,
        private ?int $reviewedBy,
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
            'school_id' => $this->schoolId,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'reviewed_by' => $this->reviewedBy,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
