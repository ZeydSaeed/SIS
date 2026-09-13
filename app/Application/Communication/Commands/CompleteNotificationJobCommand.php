<?php

namespace App\Application\Communication\Commands;

use App\Application\Contracts\Command;

final readonly class CompleteNotificationJobCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $notificationJobId,
        public int $sentCount,
        public ?string $idempotencyKey,
    ) {}
}
