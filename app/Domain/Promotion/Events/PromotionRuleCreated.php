<?php

namespace App\Domain\Promotion\Events;

use App\Domain\Shared\DomainEvent;

final readonly class PromotionRuleCreated implements DomainEvent
{
    public function __construct(
        private int $ruleId,
        private int $schoolId,
        private int $fromGradeLevelId,
        private int $toGradeLevelId,
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
            'rule_id' => $this->ruleId,
            'school_id' => $this->schoolId,
            'from_grade_level_id' => $this->fromGradeLevelId,
            'to_grade_level_id' => $this->toGradeLevelId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
