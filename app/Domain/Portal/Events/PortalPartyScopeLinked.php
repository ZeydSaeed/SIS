<?php

namespace App\Domain\Portal\Events;

use App\Domain\Shared\DomainEvent;

final readonly class PortalPartyScopeLinked implements DomainEvent
{
    public function __construct(
        private int $scopeRowId,
        private int $schoolId,
        private int $userId,
        private string $scopeType,
        private int $scopeId,
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
            'scope_row_id' => $this->scopeRowId,
            'school_id' => $this->schoolId,
            'user_id' => $this->userId,
            'scope_type' => $this->scopeType,
            'scope_id' => $this->scopeId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
