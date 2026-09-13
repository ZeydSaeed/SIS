<?php

namespace App\Domain\Curriculum\Events;

use App\Domain\Shared\DomainEvent;

final readonly class CurriculumSpecializationUpdated implements DomainEvent
{
    public function __construct(
        private int $curriculumId,
        private int $schoolId,
        private ?int $specializationId,
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
            'curriculum_id' => $this->curriculumId,
            'school_id' => $this->schoolId,
            'specialization_id' => $this->specializationId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
