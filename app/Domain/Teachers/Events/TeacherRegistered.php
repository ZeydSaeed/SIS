<?php

namespace App\Domain\Teachers\Events;

use App\Domain\Shared\DomainEvent;

final readonly class TeacherRegistered implements DomainEvent
{
    public function __construct(
        private int $teacherId,
        private int $schoolId,
        private int $academicYearId,
        private string $employeeCode,
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
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'employee_code' => $this->employeeCode,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
