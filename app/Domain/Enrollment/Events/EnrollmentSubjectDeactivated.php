<?php

namespace App\Domain\Enrollment\Events;

use App\Domain\Shared\DomainEvent;

final readonly class EnrollmentSubjectDeactivated implements DomainEvent
{
    public function __construct(
        private int $linkId,
        private int $enrollmentId,
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
            'link_id' => $this->linkId,
            'enrollment_id' => $this->enrollmentId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
