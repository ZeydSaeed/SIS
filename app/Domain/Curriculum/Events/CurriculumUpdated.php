<?php

namespace App\Domain\Curriculum\Events;

use App\Domain\Shared\DomainEvent;

final readonly class CurriculumUpdated implements DomainEvent
{
    /**
     * @param  array{name?: string, specialization_id?: ?int}  $fields
     */
    public function __construct(
        private int $curriculumId,
        private int $schoolId,
        private array $fields,
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
            'fields' => $this->fields,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
