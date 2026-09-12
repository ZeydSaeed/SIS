<?php

namespace App\Application\Communication\DTOs;

final readonly class NotificationTemplateDTO
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public string $code,
        public string $name,
        public int $channel,
        public ?string $subjectTemplate,
        public string $bodyTemplate,
        public bool $isActive,
        public string $createdAt,
    ) {}
}
