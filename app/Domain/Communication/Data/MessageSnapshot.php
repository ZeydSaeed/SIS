<?php

namespace App\Domain\Communication\Data;

final readonly class MessageSnapshot
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public ?int $templateId,
        public string $recipientType,
        public int $recipientId,
        public int $channel,
        public ?string $subject,
        public string $body,
        public int $status,
        public ?string $sentAt,
        public string $idempotencyKey,
        public string $createdAt,
    ) {}
}
