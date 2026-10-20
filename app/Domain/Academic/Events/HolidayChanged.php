<?php

namespace App\Domain\Academic\Events;

use App\Domain\Shared\DomainEvent;

/** «الاجازات»: a school holiday was created, updated, taken out of the calendar or put back. */
final readonly class HolidayChanged implements DomainEvent
{
    public function __construct(
        private int $holidayId,
        private int $schoolId,
        private string $change,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function holidayId(): int
    {
        return $this->holidayId;
    }

    public function schoolId(): int
    {
        return $this->schoolId;
    }

    public function change(): string
    {
        return $this->change;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'holiday_id' => $this->holidayId,
            'school_id' => $this->schoolId,
            'change' => $this->change,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
