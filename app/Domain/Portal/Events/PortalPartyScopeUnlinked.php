<?php

namespace App\Domain\Portal\Events;

use App\Domain\Shared\DomainEvent;

final readonly class PortalPartyScopeUnlinked implements DomainEvent
{
    public function __construct(
        private int $schoolId,
        private int $userId,
        private string $scopeType,
        private int $scopeId,
        private bool $wasPresent,
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
            'school_id' => $this->schoolId,
            'user_id' => $this->userId,
            'scope_type' => $this->scopeType,
            'scope_id' => $this->scopeId,
            'was_present' => $this->wasPresent,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
