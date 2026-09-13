<?php

namespace App\Domain\Curriculum\Events;

use App\Domain\Shared\DomainEvent;

final readonly class SubjectPrerequisiteAdded implements DomainEvent
{
    public function __construct(
        private int $prerequisiteId,
        private int $subjectId,
        private int $prerequisiteSubjectId,
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
            'prerequisite_id' => $this->prerequisiteId,
            'subject_id' => $this->subjectId,
            'prerequisite_subject_id' => $this->prerequisiteSubjectId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
