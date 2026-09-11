<?php

namespace App\Domain\Attendance\Events;

use App\Domain\Shared\DomainEvent;

final readonly class AttendanceCorrected implements DomainEvent
{
    public function __construct(
        private int $recordId,
        private int $sessionId,
        private int $studentId,
        private int $enrollmentId,
        private int $schoolId,
        private int $academicYearId,
        private int $previousStatus,
        private int $newStatus,
        private ?string $previousNotes,
        private ?string $newNotes,
        private string $reason,
        private ?int $recordedBy,
        private ?string $idempotencyKey,
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
            'record_id' => $this->recordId,
            'session_id' => $this->sessionId,
            'student_id' => $this->studentId,
            'enrollment_id' => $this->enrollmentId,
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'previous_notes' => $this->previousNotes,
            'new_notes' => $this->newNotes,
            'reason' => $this->reason,
            'recorded_by' => $this->recordedBy,
            'idempotency_key' => $this->idempotencyKey,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
