<?php

namespace App\Application\Audit\Commands;

use App\Application\Contracts\Command;

final readonly class RegisterAuditLogCommand implements Command
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function __construct(
        public int $schoolId,
        public string $action,
        public string $entityType,
        public ?int $entityId,
        public ?array $oldValues,
        public ?array $newValues,
        public ?int $userId,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $correlationId,
        public ?string $idempotencyKey,
    ) {}
}
