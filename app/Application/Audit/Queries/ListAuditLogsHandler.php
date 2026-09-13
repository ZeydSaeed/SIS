<?php

namespace App\Application\Audit\Queries;

use App\Application\Audit\DTOs\AuditLogDTO;
use App\Domain\Audit\Repositories\AuditLogRepositoryInterface;

final class ListAuditLogsHandler
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogs,
    ) {}

    /** @return list<AuditLogDTO> */
    public function handle(ListAuditLogsQuery $query): array
    {
        $limit = min(max($query->limit, 1), 100);
        $rows = $this->auditLogs->listForSchool(
            $query->schoolId,
            $query->entityType,
            $query->entityId,
            $limit,
        );

        return array_map(static fn ($row): AuditLogDTO => new AuditLogDTO(
            id: $row->id,
            schoolId: $row->schoolId,
            userId: $row->userId,
            action: $row->action,
            entityType: $row->entityType,
            entityId: $row->entityId,
            oldValues: $row->oldValues,
            newValues: $row->newValues,
            ipAddress: $row->ipAddress,
            userAgent: $row->userAgent,
            correlationId: $row->correlationId,
            createdAt: $row->createdAt,
        ), $rows);
    }
}
