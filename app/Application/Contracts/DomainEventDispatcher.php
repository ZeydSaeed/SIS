<?php

namespace App\Application\Contracts;

use App\Domain\Shared\DomainEvent;

interface DomainEventDispatcher
{
    public function dispatch(DomainEvent $event): void;
}
