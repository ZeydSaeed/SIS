<?php

namespace App\Domain\Teachers\Events;

use App\Domain\Shared\DomainEvent;

final readonly class TeacherSubjectUnlinked implements DomainEvent
{
    public function __construct(
        private int $teacherId,
        private int $subjectId,
        private int $schoolId,
        private int $academicYearId,
        private bool $wasPresent,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'teacher_id' => $this->teacherId,
            'subject_id' => $this->subjectId,
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'was_present' => $this->wasPresent,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
