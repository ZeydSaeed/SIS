<?php

namespace App\Domain\Exams\Events;

use App\Domain\Shared\DomainEvent;

/**
 * Shared enrollment-updated event — cause distinguishes U07 update vs U09 present.
 */
final readonly class ExamEnrollmentUpdated implements DomainEvent
{
    /**
     * @param  array{status?: int, seat_number?: string|null}  $changedFields
     */
    public function __construct(
        private int $examEnrollmentId,
        private int $examSessionId,
        private int $examId,
        private int $schoolId,
        private int $enrollmentId,
        private int $previousStatus,
        private int $status,
        private ?string $seatNumber,
        private array $changedFields,
        private ?int $updatedBy,
        private \DateTimeImmutable $occurredAt,
        private string $cause = 'exam_enrollment_update',
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
            'previous_status' => $this->previousStatus,
            'status' => $this->status,
            'seat_number' => $this->seatNumber,
            'changed_fields' => $this->changedFields,
            'updated_by' => $this->updatedBy,
            'cause' => $this->cause,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
