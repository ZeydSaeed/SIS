<?php

namespace App\Domain\Attendance\Events;

use App\Domain\Shared\DomainEvent;

final readonly class AttendanceSessionClosed implements DomainEvent
{
    public function __construct(
        private int $sessionId,
        private int $schoolId,
        private int $academicYearId,
        private ?int $closedBy,
        private \DateTimeImmutable $occurredAt,
    ) {}

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
            'session_id' => $this->sessionId,
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'closed_by' => $this->closedBy,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
