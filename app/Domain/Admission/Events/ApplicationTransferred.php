<?php

namespace App\Domain\Admission\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ApplicationTransferred implements DomainEvent
{
    public function __construct(
        private int $applicationId,
        private int $fromSchoolId,
        private int $toSchoolId,
        private int $toRequestKind,
        private int $toPeriodId,
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
            'from_school_id' => $this->fromSchoolId,
            'to_school_id' => $this->toSchoolId,
            'to_request_kind' => $this->toRequestKind,
            'to_period_id' => $this->toPeriodId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
