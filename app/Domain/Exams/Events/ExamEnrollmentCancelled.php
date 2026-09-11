<?php

namespace App\Domain\Exams\Events;

use App\Domain\Shared\DomainEvent;

/**
 * Shared enrollment-cancelled event — cause distinguishes cascade vs direct session cancel.
 */
final readonly class ExamEnrollmentCancelled implements DomainEvent
{
    public function __construct(
        private int $examEnrollmentId,
        private int $examSessionId,
        private int $examId,
        private int $schoolId,
        private int $previousStatus,
        private ?int $cancelledBy,
        private \DateTimeImmutable $occurredAt,
        private string $cause = 'exam_cancel',
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
            'exam_id' => $this->examId,
            'school_id' => $this->schoolId,
            'previous_status' => $this->previousStatus,
            'cancelled_by' => $this->cancelledBy,
            'cause' => $this->cause,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
