<?php

namespace App\Domain\Teachers\Events;

use App\Domain\Shared\DomainEvent;

final readonly class TeacherQualificationVoided implements DomainEvent
{
    public function __construct(
        private int $qualificationId,
        private int $teacherId,
        private int $schoolId,
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
            'qualification_id' => $this->qualificationId,
            'teacher_id' => $this->teacherId,
            'school_id' => $this->schoolId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
