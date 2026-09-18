<?php

namespace App\Domain\Admission\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ApplicationDocumentRegistered implements DomainEvent
{
    public function __construct(
        private int $documentId,
        private int $applicationId,
        private int $schoolId,
        private int $documentType,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'document_id' => $this->documentId,
            'application_id' => $this->applicationId,
            'school_id' => $this->schoolId,
            'document_type' => $this->documentType,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
