<?php

namespace App\Domain\Communication\Events;

use App\Domain\Shared\DomainEvent;

final readonly class NotificationTemplateDeactivated implements DomainEvent
{
    public function __construct(
        private int $templateId,
        private int $schoolId,
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
            'template_id' => $this->templateId,
            'school_id' => $this->schoolId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
