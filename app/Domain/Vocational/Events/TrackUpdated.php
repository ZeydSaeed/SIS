<?php

namespace App\Domain\Vocational\Events;

use App\Domain\Shared\DomainEvent;

final readonly class TrackUpdated implements DomainEvent
{
    public function __construct(
        private int $trackId,
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
            'track_id' => $this->trackId,
            'school_id' => $this->schoolId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
