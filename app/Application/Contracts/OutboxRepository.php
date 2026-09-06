<?php

namespace App\Application\Contracts;

use App\Domain\Shared\DomainEvent;

interface OutboxRepository
{
    public function stage(DomainEvent $event, ?string $correlationId = null): void;
}
