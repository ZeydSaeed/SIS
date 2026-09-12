<?php

namespace App\Domain\Academic\Events;

use App\Domain\Shared\DomainEvent;

final readonly class AcademicYearCreated implements DomainEvent
{
    public function __construct(
        private int $academicYearId,
        private string $code,
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
            'academic_year_id' => $this->academicYearId,
            'code' => $this->code,
            'cause' => 'academic_year_create',
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
