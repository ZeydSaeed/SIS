<?php

namespace App\Domain\Vocational\Events;

use App\Domain\Shared\DomainEvent;

final readonly class SpecializationSubjectLinked implements DomainEvent
{
    public function __construct(
        private int $linkId,
        private int $schoolId,
        private int $specializationId,
        private int $subjectId,
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
            'school_id' => $this->schoolId,
            'specialization_id' => $this->specializationId,
            'subject_id' => $this->subjectId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
