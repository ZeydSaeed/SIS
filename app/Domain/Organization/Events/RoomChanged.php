<?php

namespace App\Domain\Organization\Events;

use App\Domain\Shared\DomainEvent;

/** «الغرف الدراسية»: a room or a room type was created, updated, taken out of / back into service, or re-styled. */
final readonly class RoomChanged implements DomainEvent
{
    public function __construct(
        private string $target,
        private int $id,
        private int $schoolId,
        private string $change,
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
            'target' => $this->target,
            'id' => $this->id,
            'school_id' => $this->schoolId,
            'change' => $this->change,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
