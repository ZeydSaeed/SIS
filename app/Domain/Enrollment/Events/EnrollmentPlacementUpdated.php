<?php

namespace App\Domain\Enrollment\Events;

use App\Domain\Shared\DomainEvent;

final readonly class EnrollmentPlacementUpdated implements DomainEvent
{
    public function __construct(
        private int $enrollmentId,
        private int $studentId,
        private int $schoolId,
        private int $academicYearId,
        private int $previousClassId,
        private int $previousSectionId,
        private int $classId,
        private int $sectionId,
        private ?int $specializationId,
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
            'enrollment_id' => $this->enrollmentId,
            'student_id' => $this->studentId,
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'previous_class_id' => $this->previousClassId,
            'previous_section_id' => $this->previousSectionId,
            'class_id' => $this->classId,
            'section_id' => $this->sectionId,
            'specialization_id' => $this->specializationId,
            'updated_by' => $this->updatedBy,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
