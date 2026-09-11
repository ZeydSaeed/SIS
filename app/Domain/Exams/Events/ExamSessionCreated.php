<?php

namespace App\Domain\Exams\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ExamSessionCreated implements DomainEvent
{
    public function __construct(
        private int $examSessionId,
        private int $examId,
        private int $schoolId,
        private int $subjectId,
        private string $sessionDate,
        private string $startTime,
        private string $endTime,
        private ?int $roomId,
        private int $maxGrade,
        private int $passGrade,
        private int $status,
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
            'exam_session_id' => $this->examSessionId,
            'exam_id' => $this->examId,
            'school_id' => $this->schoolId,
            'subject_id' => $this->subjectId,
            'session_date' => $this->sessionDate,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'room_id' => $this->roomId,
            'max_grade' => $this->maxGrade,
            'pass_grade' => $this->passGrade,
            'status' => $this->status,
            'created_by' => $this->createdBy,
            'cause' => 'exam_session_create',
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
