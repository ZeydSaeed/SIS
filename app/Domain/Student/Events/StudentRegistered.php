<?php

namespace App\Domain\Student\Events;

use App\Domain\Shared\DomainEvent;

final readonly class StudentRegistered implements DomainEvent
{
    public function __construct(
        private int $studentId,
        private string $studentCode,
        private string $fullName,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function studentId(): int
    {
        return $this->studentId;
    }

    public function studentCode(): string
    {
        return $this->studentCode;
    }

    public function fullName(): string
    {
        return $this->fullName;
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
            'student_code' => $this->studentCode,
            'full_name' => $this->fullName,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
