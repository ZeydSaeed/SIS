<?php

namespace App\Infrastructure\Events;

use App\Domain\Attendance\Events\AttendanceWorkflowEvent;
use Illuminate\Foundation\Events\Dispatchable;

final class AttendanceWorkflowBridgeEvent
{
    use Dispatchable;

    public function __construct(
        public readonly AttendanceWorkflowEvent $domainEvent,
    ) {}
}
