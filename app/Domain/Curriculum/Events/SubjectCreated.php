<?php

namespace App\Domain\Curriculum\Events;

use App\Domain\Shared\DomainEvent;

final readonly class SubjectCreated implements DomainEvent
{
    public function __construct(
        private int $subjectId,
        private string $code,
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
            'subject_id' => $this->subjectId,
            'code' => $this->code,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
