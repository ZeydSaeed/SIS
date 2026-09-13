<?php

namespace App\Application\Audit\Queries;

use App\Application\Audit\DTOs\AuditLogDTO;
use App\Domain\Audit\Repositories\AuditLogRepositoryInterface;

final class GetAuditLogHandler
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogs,
    ) {}

    public function handle(GetAuditLogQuery $query): ?AuditLogDTO
    {
        $row = $this->auditLogs->findByIdForSchool($query->schoolId, $query->auditLogId);
        if ($row === null) {
            return null;
        }

        return new AuditLogDTO(
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
        );
    }
}
