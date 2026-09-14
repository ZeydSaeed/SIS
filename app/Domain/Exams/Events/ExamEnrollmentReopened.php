<?php

namespace App\Domain\Exams\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ExamEnrollmentReopened implements DomainEvent
{
    public function __construct(
        private int $examEnrollmentId,
        private int $examSessionId,
        private int $enrollmentId,
        private int $schoolId,
        private int $previousStatus,
        private int $status,
        private ?int $reopenedBy,
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
            'exam_enrollment_id' => $this->examEnrollmentId,
            'exam_session_id' => $this->examSessionId,
            'enrollment_id' => $this->enrollmentId,
            'school_id' => $this->schoolId,
            'previous_status' => $this->previousStatus,
            'status' => $this->status,
            'reopened_by' => $this->reopenedBy,
            'cause' => 'exam_enrollment_reopen',
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
