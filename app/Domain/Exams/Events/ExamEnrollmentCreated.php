<?php

namespace App\Domain\Exams\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ExamEnrollmentCreated implements DomainEvent
{
    public function __construct(
        private int $examEnrollmentId,
        private int $examSessionId,
        private int $examId,
        private int $schoolId,
        private int $enrollmentId,
        private int $status,
        private ?string $seatNumber,
        private ?int $createdBy,
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
            'exam_id' => $this->examId,
            'school_id' => $this->schoolId,
            'enrollment_id' => $this->enrollmentId,
            'status' => $this->status,
            'seat_number' => $this->seatNumber,
            'created_by' => $this->createdBy,
            'cause' => 'exam_enrollment_create',
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
