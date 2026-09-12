<?php

namespace App\Domain\Timetable\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ScheduleCancelled implements DomainEvent
{
    public function __construct(
        private int $scheduleId,
        private int $schoolId,
        private int $academicYearId,
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
            'schedule_id' => $this->scheduleId,
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
