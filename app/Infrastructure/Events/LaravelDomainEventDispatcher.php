<?php

namespace App\Infrastructure\Events;

use App\Application\Contracts\DomainEventDispatcher;
use App\Domain\Enrollment\Events\StudentEnrolled;
use App\Domain\Shared\DomainEvent;
use Illuminate\Support\Facades\Event;

final class LaravelDomainEventDispatcher implements DomainEventDispatcher
{
    public function dispatch(DomainEvent $event): void
    {
        if ($event instanceof StudentEnrolled) {
            Event::dispatch(new StudentEnrolledBridgeEvent($event));
        }
    }
}
