<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;

final readonly class CancelAttendanceSessionCommand implements Command
{
    public function __construct(
        public int $sessionId,
        public int $schoolId,
        public string $reason,
        public ?int $cancelledBy,
        public string $idempotencyKey,
    ) {}
}
