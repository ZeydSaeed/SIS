<?php

namespace App\Domain\Exams\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ExamCancelled implements DomainEvent
{
    /**
     * @param  list<int>  $cancelledSessionIds
     * @param  list<int>  $withdrawnEnrollmentIds
     */
    public function __construct(
        private int $examId,
        private int $schoolId,
        private int $academicYearId,
        private array $cancelledSessionIds,
        private array $withdrawnEnrollmentIds,
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
            'exam_id' => $this->examId,
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'cancelled_session_ids' => $this->cancelledSessionIds,
            'withdrawn_enrollment_ids' => $this->withdrawnEnrollmentIds,
            'cancelled_by' => $this->cancelledBy,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
