<?php

namespace App\Domain\Curriculum\Events;

use App\Domain\Shared\DomainEvent;

final readonly class CurriculumSubjectLinked implements DomainEvent
{
    public function __construct(
        private int $linkId,
        private int $curriculumId,
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
            'curriculum_id' => $this->curriculumId,
            'subject_id' => $this->subjectId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
