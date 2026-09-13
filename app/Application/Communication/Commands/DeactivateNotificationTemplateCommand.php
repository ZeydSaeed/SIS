<?php

namespace App\Application\Communication\Commands;

use App\Application\Contracts\Command;

final readonly class DeactivateNotificationTemplateCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $templateId,
        public ?string $idempotencyKey,
    ) {}
}
