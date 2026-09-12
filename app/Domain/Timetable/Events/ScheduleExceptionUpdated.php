<?php

namespace App\Domain\Timetable\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ScheduleExceptionUpdated implements DomainEvent
{
    public function __construct(
        private int $exceptionId,
        private int $schoolId,
        private int $scheduleId,
        private string $exceptionDate,
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
            'exception_id' => $this->exceptionId,
            'school_id' => $this->schoolId,
            'schedule_id' => $this->scheduleId,
            'exception_date' => $this->exceptionDate,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
