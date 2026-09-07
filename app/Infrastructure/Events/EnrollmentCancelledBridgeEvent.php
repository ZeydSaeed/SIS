<?php

namespace App\Infrastructure\Events;

use App\Domain\Enrollment\Events\EnrollmentCancelled;

final class EnrollmentCancelledBridgeEvent
{
    public function __construct(
        public readonly EnrollmentCancelled $domainEvent,
    ) {}
}
