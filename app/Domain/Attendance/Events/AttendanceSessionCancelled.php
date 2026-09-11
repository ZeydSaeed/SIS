<?php

namespace App\Domain\Attendance\Events;

use App\Domain\Shared\DomainEvent;

final readonly class AttendanceSessionCancelled implements DomainEvent
{
    public function __construct(
        private int $sessionId,
        private int $schoolId,
        private int $academicYearId,
        private int $previousStatus,
        private int $newStatus,
        private string $reason,
        private ?int $cancelledBy,
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
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'reason' => $this->reason,
            'cancelled_by' => $this->cancelledBy,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
