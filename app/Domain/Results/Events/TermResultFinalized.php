<?php

namespace App\Domain\Results\Events;

use App\Domain\Shared\DomainEvent;

final readonly class TermResultFinalized implements DomainEvent
{
    public function __construct(
        private int $termResultId,
        private int $schoolId,
        private int $enrollmentId,
        private int $studentId,
        private int $academicYearId,
        private int $termId,
        private int $subjectId,
        private int $resultVersion,
        private ?string $weightedTotal,
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
            'term_result_id' => $this->termResultId,
            'school_id' => $this->schoolId,
            'enrollment_id' => $this->enrollmentId,
            'student_id' => $this->studentId,
            'academic_year_id' => $this->academicYearId,
            'term_id' => $this->termId,
            'subject_id' => $this->subjectId,
            'result_version' => $this->resultVersion,
            'weighted_total' => $this->weightedTotal,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
