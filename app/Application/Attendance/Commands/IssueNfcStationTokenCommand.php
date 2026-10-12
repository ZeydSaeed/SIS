<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;

/** Issue a long-lived NFC station token (written once to a tag at the school entrance). */
final readonly class IssueNfcStationTokenCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $label,
        public int $actorUserId,
        public string $idempotencyKey,
    ) {}
}
