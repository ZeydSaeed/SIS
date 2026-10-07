<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** «أرشفة»: a draft / rejected / approved / superseded version leaves the list of working versions (never deleted). */
final readonly class ArchiveTimetableVersionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $versionId,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}
}
