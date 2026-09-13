<?php

namespace App\Domain\Student\Events;

use App\Domain\Shared\DomainEvent;

final readonly class StudentDocumentRegistered implements DomainEvent
{
    public function __construct(
        private int $documentId,
        private int $schoolId,
        private int $studentId,
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
            'student_id' => $this->studentId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
