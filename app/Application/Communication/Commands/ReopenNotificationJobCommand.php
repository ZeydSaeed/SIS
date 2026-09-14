<?php

namespace App\Application\Communication\Commands;

use App\Application\Contracts\Command;

final readonly class ReopenNotificationJobCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $notificationJobId,
        public ?string $idempotencyKey,
    ) {}
}
