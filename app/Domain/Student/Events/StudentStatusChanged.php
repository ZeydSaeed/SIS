<?php

namespace App\Domain\Student\Events;

use App\Domain\Shared\DomainEvent;

final readonly class StudentStatusChanged implements DomainEvent
{
    public function __construct(
        private int $studentId,
        private int $schoolId,
        private int $fromStatus,
        private int $toStatus,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function studentId(): int
    {
        return $this->studentId;
    }

    public function schoolId(): int
    {
        return $this->schoolId;
    }

    public function fromStatus(): int
    {
        return $this->fromStatus;
    }

    public function toStatus(): int
    {
        return $this->toStatus;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'student_id' => $this->studentId,
            'school_id' => $this->schoolId,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
