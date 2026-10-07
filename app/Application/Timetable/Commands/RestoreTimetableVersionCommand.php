<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/**
 * «استعادة إلى مساحة العمل» (rollback): the working grid becomes a copy of the version again — current unlocked
 * lessons are cancelled (history), the version's lessons inserted. Locked lessons stay; a clash with one is refused.
 */
final readonly class RestoreTimetableVersionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $versionId,
        public ?int $userId,
        public ?string $correlationId,
        public ?string $idempotencyKey = null,
    ) {}
}
