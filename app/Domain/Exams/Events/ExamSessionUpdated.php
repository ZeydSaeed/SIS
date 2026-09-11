<?php

namespace App\Domain\Exams\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ExamSessionUpdated implements DomainEvent
{
    /**
     * @param  array<string, mixed>  $changedFields
     */
    public function __construct(
        private int $examSessionId,
        private int $examId,
        private int $schoolId,
        private int $status,
        private array $changedFields,
        private ?int $updatedBy,
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
            'status' => $this->status,
            'changed_fields' => $this->changedFields,
            'updated_by' => $this->updatedBy,
            'cause' => 'exam_session_update',
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
