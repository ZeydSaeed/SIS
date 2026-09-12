<?php

namespace App\Application\Communication\Commands;

use App\Application\Contracts\Command;

final readonly class CreateNotificationTemplateCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public int $channel,
        public ?string $subjectTemplate,
        public string $bodyTemplate,
        public bool $isActive = true,
        public ?string $idempotencyKey = null,
    ) {}
}
