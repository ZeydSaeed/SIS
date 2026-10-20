<?php

namespace App\Listeners\Leave;

use App\Application\Leave\Commands\NotifyLeaveHolidayChangeCommand;
use App\Application\Leave\Commands\NotifyLeaveHolidayChangeHandler;
use App\Infrastructure\Events\HolidayChangedBridgeEvent;
use App\Security\Context\SchoolContextScope;

/**
 * After commit (outbox): a holiday changed → the leave managers are told which open requests it touches. Runs in the
 * holiday's school context (RLS); the notice is the only effect — no request, duration or balance is rewritten.
 */
final class NotifyLeaveOfHolidayChange
{
    public function __construct(
        private readonly NotifyLeaveHolidayChangeHandler $handler,
        private readonly SchoolContextScope $scope,
    ) {}

    public function handle(HolidayChangedBridgeEvent $bridge): void
    {
        $event = $bridge->domainEvent;
        $this->scope->run($event->schoolId(), fn () => $this->handler->handle(new NotifyLeaveHolidayChangeCommand($event->schoolId(), $event->holidayId())));
    }
}
