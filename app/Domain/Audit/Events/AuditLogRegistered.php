<?php

namespace App\Domain\Audit\Events;

use App\Domain\Shared\DomainEvent;

final readonly class AuditLogRegistered implements DomainEvent
{
    public function __construct(
        private int $auditLogId,
        private int $schoolId,
        private string $action,
        private string $entityType,
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
            'audit_log_id' => $this->auditLogId,
            'school_id' => $this->schoolId,
            'action' => $this->action,
            'entity_type' => $this->entityType,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
