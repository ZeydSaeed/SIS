<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;

final readonly class CloseAttendanceSessionCommand implements Command
{
    public function __construct(
        public int $sessionId,
        public int $schoolId,
        public ?int $closedBy = null,
        public ?string $idempotencyKey = null,
    ) {}
}
