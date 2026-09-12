<?php

namespace App\Domain\Timetable\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ScheduleCreated implements DomainEvent
{
    public function __construct(
        private int $scheduleId,
        private int $schoolId,
        private int $sectionId,
        private int $academicYearId,
        private int $dayOfWeek,
        private int $periodId,
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
            'section_id' => $this->sectionId,
            'academic_year_id' => $this->academicYearId,
            'day_of_week' => $this->dayOfWeek,
            'period_id' => $this->periodId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
