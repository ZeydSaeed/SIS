<?php

namespace App\Domain\Attendance\Events;

use App\Domain\Shared\DomainEvent;

final readonly class AttendanceSessionCreated implements DomainEvent
{
    public function __construct(
        private int $sessionId,
        private int $schoolId,
        private int $academicYearId,
        private int $sectionId,
        private int $subjectId,
        private string $sessionDate,
        private int $teacherId,
        private ?int $periodId,
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
            'session_id' => $this->sessionId,
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'section_id' => $this->sectionId,
            'subject_id' => $this->subjectId,
            'session_date' => $this->sessionDate,
            'teacher_id' => $this->teacherId,
            'period_id' => $this->periodId,
            'created_by' => $this->createdBy,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
