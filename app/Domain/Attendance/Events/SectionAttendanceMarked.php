<?php

namespace App\Domain\Attendance\Events;

use App\Domain\Shared\DomainEvent;

final readonly class SectionAttendanceMarked implements DomainEvent
{
    /**
     * @param  list<int>  $studentIds
     */
    public function __construct(
        private int $sessionId,
        private int $schoolId,
        private int $academicYearId,
        private int $sectionId,
        private int $count,
        private array $studentIds,
        private ?int $recordedBy,
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
            'count' => $this->count,
            'student_ids' => $this->studentIds,
            'recorded_by' => $this->recordedBy,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
