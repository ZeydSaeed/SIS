<?php

namespace App\Application\Communication\Queries;

final readonly class ListMessagesQuery
{
    public function __construct(
        public int $schoolId,
        public ?string $recipientType = null,
        public ?int $recipientId = null,
        public ?int $messageStatus = null,
    ) {}
}
