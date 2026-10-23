<?php

namespace App\Domain\Exams\Events;

use App\Domain\Shared\DomainEvent;

/** An exam type was created or changed (academic policy: its weight drives term results). */
final readonly class ExamTypeSaved implements DomainEvent
{
    public function __construct(
        private int $examTypeId,
        private int $schoolId,
        private string $code,
        private string $name,
        private int $weightPercentage,
        private ?int $previousWeightPercentage,
        private bool $created,
        private ?int $savedBy,
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
            'exam_type_id' => $this->examTypeId,
            'school_id' => $this->schoolId,
            'code' => $this->code,
            'name' => $this->name,
            'weight_percentage' => $this->weightPercentage,
            'previous_weight_percentage' => $this->previousWeightPercentage,
            'created' => $this->created,
            'saved_by' => $this->savedBy,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
