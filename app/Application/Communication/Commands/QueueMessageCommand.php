<?php

namespace App\Application\Communication\Commands;

use App\Application\Contracts\Command;

final readonly class QueueMessageCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public string $recipientType,
        public int $recipientId,
        public int $channel,
        public string $body,
        public ?string $subject = null,
        public ?int $templateId = null,
        public ?string $idempotencyKey = null,
    ) {}
}
