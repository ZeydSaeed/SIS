<?php

namespace App\Domain\Leave\Events;

use App\Domain\Shared\DomainEvent;

/**
 * Staged in the outbox inside the write's transaction: a leave request, type, policy or balance changed. The payload
 * carries ids and statuses only — never the reason or attachments (they may be medical).
 */
final readonly class LeaveChanged implements DomainEvent
{
    /**
     * @param  array<string, scalar|null>  $details
     */
    public function __construct(
        private string $target,
        private int $id,
        private int $schoolId,
        private string $change,
        private ?int $actorUserId,
        private array $details,
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
            'actor_user_id' => $this->actorUserId,
            'details' => $this->details,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
