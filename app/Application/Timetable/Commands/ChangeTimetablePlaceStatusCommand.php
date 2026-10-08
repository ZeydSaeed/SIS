<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** Takes a room / workshop out of service (status 2) or back (status 1). `kind` is `room` or `workshop`. */
final readonly class ChangeTimetablePlaceStatusCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public string $kind,
        public int $placeId,
        public int $status,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}
}
