<?php

namespace App\Domain\Exams\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ExamCreated implements DomainEvent
{
    public function __construct(
        private int $examId,
        private int $schoolId,
        private int $academicYearId,
        private int $termId,
        private int $examTypeId,
        private string $name,
        private string $startDate,
        private string $endDate,
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
            'exam_id' => $this->examId,
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'term_id' => $this->termId,
            'exam_type_id' => $this->examTypeId,
            'name' => $this->name,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'status' => $this->status,
            'created_by' => $this->createdBy,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
