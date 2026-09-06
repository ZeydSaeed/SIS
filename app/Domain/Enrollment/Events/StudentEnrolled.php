<?php

namespace App\Domain\Enrollment\Events;

use App\Domain\Shared\DomainEvent;

final readonly class StudentEnrolled implements DomainEvent
{
    public function __construct(
        private int $enrollmentId,
        private int $studentId,
        private int $schoolId,
        private int $academicYearId,
        private int $classId,
        private int $sectionId,
        private string $enrollmentNumber,
        private ?int $enrolledBy,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function enrollmentId(): int
    {
        return $this->enrollmentId;
    }

    public function studentId(): int
    {
        return $this->studentId;
    }

    public function schoolId(): int
    {
        return $this->schoolId;
    }

    public function academicYearId(): int
    {
        return $this->academicYearId;
    }

    public function classId(): int
    {
        return $this->classId;
    }

    public function sectionId(): int
    {
        return $this->sectionId;
    }

    public function enrollmentNumber(): string
    {
        return $this->enrollmentNumber;
    }

    public function enrolledBy(): ?int
    {
        return $this->enrolledBy;
    }

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
            'enrollment_id' => $this->enrollmentId,
            'student_id' => $this->studentId,
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'class_id' => $this->classId,
            'section_id' => $this->sectionId,
            'enrollment_number' => $this->enrollmentNumber,
            'enrolled_by' => $this->enrolledBy,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
