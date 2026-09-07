<?php

namespace App\Infrastructure\Events;

use App\Domain\Enrollment\Events\EnrollmentPlacementUpdated;

final class EnrollmentPlacementUpdatedBridgeEvent
{
    public function __construct(
        public readonly EnrollmentPlacementUpdated $domainEvent,
    ) {}
}
