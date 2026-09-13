<?php

namespace App\Domain\Vocational\Events;

use App\Domain\Shared\DomainEvent;

final readonly class WorkshopCreated implements DomainEvent
{
    public function __construct(
        private int $workshopId,
        private int $schoolId,
        private string $code,
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
            'workshop_id' => $this->workshopId,
            'school_id' => $this->schoolId,
            'code' => $this->code,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
