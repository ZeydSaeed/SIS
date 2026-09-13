<?php

namespace App\Domain\Hr\Events;

use App\Domain\Shared\DomainEvent;

final readonly class JobPositionReactivated implements DomainEvent
{
    public function __construct(
        private int $jobPositionId,
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
            'job_position_id' => $this->jobPositionId,
            'school_id' => $this->schoolId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
