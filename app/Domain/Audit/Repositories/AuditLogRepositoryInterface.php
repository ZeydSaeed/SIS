<?php

namespace App\Domain\Audit\Repositories;

use App\Domain\Audit\Data\AuditLogSnapshot;

interface AuditLogRepositoryInterface
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function create(
        int $schoolId,
        ?int $userId,
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $oldValues,
        ?array $newValues,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $correlationId,
        string $createdAt,
    ): int;

    /**
     * @return list<AuditLogSnapshot>
     */
    public function listForSchool(
        int $schoolId,
        ?string $entityType,
        ?int $entityId,
        int $limit,
    ): array;
}
