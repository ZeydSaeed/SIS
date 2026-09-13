<?php

namespace App\Domain\Teachers\Events;

use App\Domain\Shared\DomainEvent;

final readonly class TeacherPrimarySchoolChanged implements DomainEvent
{
    public function __construct(
        private int $teacherId,
        private int $sourceSchoolId,
        private int $targetSchoolId,
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
            'teacher_id' => $this->teacherId,
            'source_school_id' => $this->sourceSchoolId,
            'target_school_id' => $this->targetSchoolId,
            'academic_year_id' => $this->academicYearId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
