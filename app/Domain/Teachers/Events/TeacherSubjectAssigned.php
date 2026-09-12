<?php

namespace App\Domain\Teachers\Events;

use App\Domain\Shared\DomainEvent;

final readonly class TeacherSubjectAssigned implements DomainEvent
{
    public function __construct(
        private int $assignmentId,
        private int $teacherId,
        private int $subjectId,
        private int $schoolId,
        private int $academicYearId,
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
            'assignment_id' => $this->assignmentId,
            'teacher_id' => $this->teacherId,
            'subject_id' => $this->subjectId,
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
