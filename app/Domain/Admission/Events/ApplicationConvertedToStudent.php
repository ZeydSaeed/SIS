<?php

namespace App\Domain\Admission\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ApplicationConvertedToStudent implements DomainEvent
{
    public function __construct(
        private int $applicationId,
        private int $studentId,
        private int $schoolId,
        private string $applicationNumber,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'application_id' => $this->applicationId,
            'student_id' => $this->studentId,
            'school_id' => $this->schoolId,
            'application_number' => $this->applicationNumber,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
