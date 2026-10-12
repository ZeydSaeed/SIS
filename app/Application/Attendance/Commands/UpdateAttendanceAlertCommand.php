<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;

/** Acknowledge (2), resolve (3) or dismiss (4) an alert after human review. */
final readonly class UpdateAttendanceAlertCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $alertId,
        public int $status,
        public ?string $note,
        public int $actorUserId,
        public string $idempotencyKey,
    ) {}
}
