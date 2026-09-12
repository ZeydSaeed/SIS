<?php

namespace App\Domain\Documents\Events;

use App\Domain\Shared\DomainEvent;

final readonly class DocumentMetadataRegistered implements DomainEvent
{
    public function __construct(
        private int $documentId,
        private int $schoolId,
        private string $entityType,
        private int $entityId,
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
            'document_id' => $this->documentId,
            'school_id' => $this->schoolId,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
