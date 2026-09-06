<?php

namespace App\Infrastructure\Events;

use App\Domain\Enrollment\Events\StudentEnrolled;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class StudentEnrolledBridgeEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly StudentEnrolled $domainEvent,
    ) {}
}
