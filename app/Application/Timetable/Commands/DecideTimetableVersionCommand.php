<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/**
 * «اعتماد / رفض» from the timetable page: decides the version's workflow request through the workflow module
 * itself (its step-role check stays authoritative); the workflow completion hook moves the version to
 * approved / rejected.
 */
final readonly class DecideTimetableVersionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $versionId,
        public string $decision,
        public int $actorUserId,
        public ?string $idempotencyKey = null,
    ) {}
}
