<?php

namespace App\Domain\Shared;

interface DomainEvent
{
    public function occurredAt(): \DateTimeImmutable;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array;
}
