<?php

namespace App\Domain\Exams\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ExamUpdated implements DomainEvent
{
    /**
     * @param  array<string, mixed>  $changedFields
     */
    public function __construct(
        private int $examId,
        private int $schoolId,
        private int $academicYearId,
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
            'exam_id' => $this->examId,
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'status' => $this->status,
            'changed_fields' => $this->changedFields,
            'updated_by' => $this->updatedBy,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
