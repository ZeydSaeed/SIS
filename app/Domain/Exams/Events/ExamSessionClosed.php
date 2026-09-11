<?php

namespace App\Domain\Exams\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ExamSessionClosed implements DomainEvent
{
    public function __construct(
        private int $examSessionId,
        private int $examId,
        private int $schoolId,
        private int $previousStatus,
        private int $status,
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
            'exam_session_id' => $this->examSessionId,
            'exam_id' => $this->examId,
            'school_id' => $this->schoolId,
            'previous_status' => $this->previousStatus,
            'status' => $this->status,
            'closed_by' => $this->closedBy,
            'cause' => 'exam_session_close',
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
