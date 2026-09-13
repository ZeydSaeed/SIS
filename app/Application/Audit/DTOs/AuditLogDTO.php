<?php

namespace App\Application\Audit\DTOs;

final readonly class AuditLogDTO
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function __construct(
        public int $id,
        public int $schoolId,
        public ?int $userId,
        public string $action,
        public string $entityType,
        public ?int $entityId,
        public ?array $oldValues,
        public ?array $newValues,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $correlationId,
        public string $createdAt,
    ) {}
}
