<?php

namespace App\Infrastructure\Events;

use App\Domain\Academic\Events\HolidayChanged;
use Illuminate\Foundation\Events\Dispatchable;

final class HolidayChangedBridgeEvent
{
    use Dispatchable;

    public function __construct(
        public readonly HolidayChanged $domainEvent,
    ) {}
}
